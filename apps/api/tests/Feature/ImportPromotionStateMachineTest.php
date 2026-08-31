<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Imports\AdoptImportItem;
use App\Actions\Imports\ClaimImportPromotion;
use App\Actions\Imports\CleanupImportPromotionObject;
use App\Actions\Imports\CreateImportDecisionSnapshot;
use App\Actions\Imports\FinalizeImportPromotion;
use App\Actions\Imports\ReconcileImportPromotion;
use App\Actions\Imports\RecordImportPromotionFailure;
use App\Actions\Imports\RequestImportPromotionCancellation;
use App\Actions\Imports\ReserveImportPromotion;
use App\Actions\Imports\VerifyImportPromotionSource;
use App\Enums\DocumentStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Enums\PromotionAttemptStatus;
use App\Enums\PromotionOperationKind;
use App\Enums\WorkspaceRole;
use App\Exceptions\ImportPromotionException;
use App\Models\Document;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\PromotionAttempt;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\Documents\DocumentObjectStorage;
use App\Services\Documents\ImportPromotionObjectStorage;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class ImportPromotionStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_item_promotes_atomically_with_version_bound_storage_and_ingestion_event(): void
    {
        [$workspace, $actor, $item] = $this->item();
        $snapshot = app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $this->definition($actor));
        $this->assertSame($snapshot->id, $item->refresh()->current_decision_snapshot_id);
        $attempt = app(ReserveImportPromotion::class)->handle($item, $actor, PromotionOperationKind::Promote, 'promote-1');
        $this->assertSame($attempt->id, app(ReserveImportPromotion::class)->handle($item, $actor, PromotionOperationKind::Promote, 'promote-1')->id);
        $claim = app(ClaimImportPromotion::class)->handle($attempt);
        $this->fakeStorageEvidence($item);
        $verified = app(VerifyImportPromotionSource::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation']);
        $this->assertSame(PromotionAttemptStatus::SourceVerified, $verified->status);

        $committed = app(FinalizeImportPromotion::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation']);

        $this->assertSame(PromotionAttemptStatus::Committed, $committed->status);
        $document = $committed->committedDocument;
        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame(DocumentStatus::Queued, $document->status);
        $this->assertSame('version-1', $document->storage_version_id);
        $this->assertSame($item->source_checksum_sha256, $document->source_checksum_sha256);
        $this->assertSame($attempt->reserved_object_key, $document->storage_key);
        $this->assertDatabaseHas('outbox_events', ['document_public_id' => $document->public_id, 'event_type' => 'document.ingestion.requested']);
        $this->assertDatabaseHas('workspace_checksum_reservations', ['workspace_id' => $workspace->id, 'source_checksum_sha256' => $item->source_checksum_sha256]);
    }

    public function test_duplicate_and_live_authorization_changes_terminalise_as_conflicts(): void
    {
        [, $actor, $item] = $this->item();
        [$attempt, $claim] = $this->sourceVerifiedAttempt($item, $actor, 'duplicate');
        Document::factory()->for($item->workspace)->create([
            'source_checksum_sha256' => $item->source_checksum_sha256,
            'checksum_verification_status' => 'verified',
            'status' => DocumentStatus::Indexed,
        ]);
        $conflict = app(FinalizeImportPromotion::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation']);
        $this->assertSame(PromotionAttemptStatus::Conflict, $conflict->status);
        $this->assertSame('duplicate', $conflict->terminal_reason);
        $this->assertNull($conflict->committed_document_id);

        [, $otherActor, $otherItem] = $this->item();
        [$otherAttempt, $otherClaim] = $this->sourceVerifiedAttempt($otherItem, $otherActor, 'authorization');
        WorkspaceMembership::query()->where('workspace_id', $otherItem->workspace_id)->where('user_id', $otherActor->id)->delete();
        $authorization = app(FinalizeImportPromotion::class)->handle($otherAttempt, $otherClaim['lease_token'], $otherClaim['lease_generation']);
        $this->assertSame(PromotionAttemptStatus::Conflict, $authorization->status);
        $this->assertSame('authorization_changed', $authorization->terminal_reason);
    }

    public function test_cancellation_failure_ceiling_and_reconciliation_are_lease_gated_and_idempotent(): void
    {
        [, $actor, $item] = $this->item();
        app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $this->definition($actor));
        $reserved = app(ReserveImportPromotion::class)->handle($item, $actor, PromotionOperationKind::Promote, 'cancel-reserved');
        $cancelled = app(RequestImportPromotionCancellation::class)->handle($reserved, $actor);
        $this->assertSame(PromotionAttemptStatus::Abandoned, $cancelled->status);
        $this->assertSame(PromotionAttemptStatus::Abandoned, app(RequestImportPromotionCancellation::class)->handle($reserved, $actor)->status);

        [, $failureActor, $failureItem] = $this->item();
        app(CreateImportDecisionSnapshot::class)->handle($failureItem, $failureActor, $this->definition($failureActor));
        $attempt = app(ReserveImportPromotion::class)->handle($failureItem, $failureActor, PromotionOperationKind::Retry, 'failure-1');
        config()->set('imports.promotion.failure_ceiling', 1);
        $claim = app(ClaimImportPromotion::class)->handle($attempt);
        $failed = app(RecordImportPromotionFailure::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation'], 'copy_failed');
        $this->assertSame(PromotionAttemptStatus::Failed, $failed->status);
        $this->assertSame(1, $failed->failures()->count());
        $this->assertDatabaseCount('promotion_attempt_failures', 1);
        $this->assertSame(PromotionAttemptStatus::Failed, app(ReconcileImportPromotion::class)->handle($failed)->status);
    }

    public function test_adoption_requires_an_authorised_new_decision_and_new_actor_identity(): void
    {
        [$workspace, $actor, $item] = $this->item();
        app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $this->definition($actor));
        $adopter = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($adopter)->create(['role' => WorkspaceRole::Admin]);
        $definition = $this->definition($adopter);
        $definition['metadata']['description'] = 'Adopted after the original actor lost authority.';
        $attempt = app(AdoptImportItem::class)->handle($item, $adopter, $definition, 'adopt-1');
        $this->assertSame(PromotionOperationKind::Adopt, $attempt->operation_kind);
        $this->assertSame($adopter->id, $attempt->actor_user_id);
        $this->assertNotSame($actor->id, $attempt->decisionSnapshot->actor_user_id);

        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->create(['role' => WorkspaceRole::Member]);
        $this->expectException(ImportPromotionException::class);
        app(AdoptImportItem::class)->handle($item, $member, $definition, 'adopt-denied');
    }

    public function test_stale_lease_cannot_write_and_a_changed_decision_terminalises_the_current_attempt(): void
    {
        [, $actor, $item] = $this->item();
        app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $this->definition($actor));
        $attempt = app(ReserveImportPromotion::class)->handle($item, $actor, PromotionOperationKind::Promote, 'lease-race');
        $firstClaim = app(ClaimImportPromotion::class)->handle($attempt);
        $attempt->refresh()->forceFill(['lease_expires_at' => now()->subSecond()])->save();
        $secondClaim = app(ClaimImportPromotion::class)->handle($attempt);

        try {
            app(VerifyImportPromotionSource::class)->handle(
                $attempt,
                $firstClaim['lease_token'],
                $firstClaim['lease_generation'],
            );
            $this->fail('A stale promotion lease wrote source-verification evidence.');
        } catch (ImportPromotionException $exception) {
            $this->assertSame('stale_promotion_lease', $exception->reason);
        }

        $this->fakeStorageEvidence($item);
        app(VerifyImportPromotionSource::class)->handle(
            $attempt,
            $secondClaim['lease_token'],
            $secondClaim['lease_generation'],
        );
        $revised = $this->definition($actor);
        $revised['metadata']['description'] = 'A new immutable review decision.';
        app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $revised);
        $result = app(FinalizeImportPromotion::class)->handle(
            $attempt,
            $secondClaim['lease_token'],
            $secondClaim['lease_generation'],
        );
        $this->assertSame(PromotionAttemptStatus::Conflict, $result->status);
        $this->assertSame('decision_changed', $result->terminal_reason);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_invalidated_existing_family_fails_closed_and_terminal_object_cleanup_is_interlocked(): void
    {
        [$workspace, $actor, $item] = $this->item();
        $predecessor = Document::factory()->for($workspace)->create([
            'effective_from' => now()->subMonth(),
            'status' => DocumentStatus::Indexed,
        ]);
        $definition = $this->definition($actor);
        $definition['family'] = [
            'mode' => 'successor',
            'family_public_id' => $predecessor->family->public_id,
        ];
        app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $definition);
        $attempt = app(ReserveImportPromotion::class)->handle($item, $actor, PromotionOperationKind::Promote, 'invalidated-family');
        $claim = app(ClaimImportPromotion::class)->handle($attempt);
        $this->fakeStorageEvidence($item);
        app(VerifyImportPromotionSource::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation']);
        $predecessor->family->forceFill(['tombstoned_at' => now()])->save();

        $result = app(FinalizeImportPromotion::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation']);
        $this->assertSame(PromotionAttemptStatus::Conflict, $result->status);
        $this->assertSame('invalidated_predecessor', $result->terminal_reason);

        $storage = Mockery::mock(ImportPromotionObjectStorage::class);
        $storage->shouldReceive('deleteVersion')->once()->with($result->reserved_object_key, 'version-1');
        $this->app->instance(ImportPromotionObjectStorage::class, $storage);
        $this->assertTrue(app(CleanupImportPromotionObject::class)->handle($result));
    }

    public function test_committed_object_ownership_prevents_promotion_cleanup(): void
    {
        [, $actor, $item] = $this->item();
        [$attempt, $claim] = $this->sourceVerifiedAttempt($item, $actor, 'cleanup-commit');
        $committed = app(FinalizeImportPromotion::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation']);
        $storage = Mockery::mock(ImportPromotionObjectStorage::class);
        $storage->shouldNotReceive('deleteVersion');
        $this->app->instance(ImportPromotionObjectStorage::class, $storage);

        $this->assertFalse(app(CleanupImportPromotionObject::class)->handle($committed));
    }

    public function test_committed_document_identity_is_structurally_bound_to_the_attempt_workspace(): void
    {
        [, $actor, $item] = $this->item();
        app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $this->definition($actor));
        $attempt = app(ReserveImportPromotion::class)->handle($item, $actor, PromotionOperationKind::Promote, 'cross-workspace-document');
        $otherDocument = Document::factory()->create();

        try {
            DB::table('promotion_attempts')->where('id', $attempt->id)->update([
                'status' => PromotionAttemptStatus::Committed->value,
                'committed_document_id' => $otherDocument->id,
            ]);
            $this->fail('A promotion attempt accepted a committed document from another workspace.');
        } catch (QueryException) {
            $this->assertSame(PromotionAttemptStatus::Reserved, $attempt->refresh()->status);
            $this->assertNull($attempt->committed_document_id);
        }
    }

    public function test_versioned_backend_reuses_verified_content_and_document_reads_remain_bound_to_the_committed_version(): void
    {
        $body = '%PDF-1.7 immutable promotion source';
        [$workspace, , $item] = $this->item($body);
        Storage::disk('s3')->put($item->staged_object_key, $body, ['ContentType' => 'application/pdf']);
        $storage = app(ImportPromotionObjectStorage::class);
        $reservedKey = $storage->reservedKey($workspace, $item);

        $first = $storage->materialise($workspace, $item, $reservedKey);
        $second = $storage->materialise($workspace, $item, $reservedKey);
        $this->assertSame($first['version_id'], $second['version_id']);
        $this->assertSame(hash('sha256', $body), $first['sha256']);

        Storage::disk('s3')->put($reservedKey, 'later mutable key content', ['ContentType' => 'application/pdf']);
        $document = new Document(['media_type' => 'application/pdf']);
        $document->storage_key = $reservedKey;
        $document->storage_version_id = $first['version_id'];
        $this->assertSame(
            ['size_bytes' => strlen($body), 'sha256' => hash('sha256', $body)],
            app(DocumentObjectStorage::class)->streamedIdentity($document),
        );

        $disk = Storage::disk('s3');
        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);
        $current = $disk->getClient()->headObject([
            'Bucket' => (string) config('filesystems.disks.s3.bucket'),
            'Key' => $reservedKey,
        ]);
        $currentVersion = (string) $current['VersionId'];
        $this->assertNotSame($first['version_id'], $currentVersion);

        $storage->deleteVersion($reservedKey, $first['version_id']);
        $storage->deleteVersion($reservedKey, $currentVersion);
        Storage::disk('s3')->delete($item->staged_object_key);
    }

    /** @return array{Workspace, User, ImportItem} */
    private function item(?string $sourceBody = null): array
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($actor)->create(['role' => WorkspaceRole::Owner]);
        $batch = ImportBatch::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'initiated_by_user_id' => $actor->id,
            'status' => ImportBatchStatus::Open,
            'retention_expires_at' => now()->addDays(7),
        ]);
        $publicId = (string) Str::uuid();
        $item = ImportItem::query()->create([
            'public_id' => $publicId,
            'import_batch_id' => $batch->id,
            'workspace_id' => $workspace->id,
            'staged_object_key' => "imports/workspaces/{$workspace->public_id}/items/{$publicId}/source",
            'source_filename' => 'Medication procedure.pdf',
            'source_checksum_sha256' => $sourceBody === null
                ? str_repeat(substr($publicId, 0, 1), 64)
                : hash('sha256', $sourceBody),
            'media_type' => 'application/pdf',
            'size_bytes' => $sourceBody === null ? 123 : strlen($sourceBody),
            'preflight_status' => ImportPreflightStatus::Verified,
            'match_status' => ImportMatchStatus::Resolved,
        ]);

        return [$workspace, $actor, $item];
    }

    /** @return array<string, mixed> */
    private function definition(User $owner): array
    {
        return [
            'family' => ['mode' => 'new', 'title' => 'Medication procedure'],
            'metadata' => [
                'category_public_id' => null,
                'description' => null,
                'owner_user_public_id' => $owner->public_id,
                'publisher_label' => 'Clinical governance',
                'review_due_date' => null,
                'source_url' => null,
                'tag_public_ids' => [],
            ],
            'applicability' => ['location_public_ids' => []],
            'effective_from' => now()->addDay()->toDateString(),
        ];
    }

    /** @return array{PromotionAttempt, array{attempt: PromotionAttempt, lease_token: string, lease_generation: int}} */
    private function sourceVerifiedAttempt(ImportItem $item, User $actor, string $key): array
    {
        app(CreateImportDecisionSnapshot::class)->handle($item, $actor, $this->definition($actor));
        $attempt = app(ReserveImportPromotion::class)->handle($item, $actor, PromotionOperationKind::Promote, $key);
        $claim = app(ClaimImportPromotion::class)->handle($attempt);
        $this->fakeStorageEvidence($item);
        app(VerifyImportPromotionSource::class)->handle($attempt, $claim['lease_token'], $claim['lease_generation']);

        return [$attempt, $claim];
    }

    private function fakeStorageEvidence(ImportItem $item): void
    {
        $storage = Mockery::mock(ImportPromotionObjectStorage::class);
        $storage->shouldReceive('reservedKey')->byDefault()->andReturnUsing(
            fn (Workspace $workspace, ImportItem $candidate): string => sprintf(
                'workspaces/%s/imports/%s/objects/%s/source',
                $workspace->public_id,
                $candidate->public_id,
                $candidate->source_checksum_sha256,
            ),
        );
        $storage->shouldReceive('materialise')->once()->andReturn([
            'proof' => 's3_version_id',
            'version_id' => 'version-1',
            'sha256' => $item->source_checksum_sha256,
            'size_bytes' => $item->size_bytes,
            'media_type' => $item->media_type,
        ]);
        $this->app->instance(ImportPromotionObjectStorage::class, $storage);
    }
}
