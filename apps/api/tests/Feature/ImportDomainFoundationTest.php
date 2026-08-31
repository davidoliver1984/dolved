<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Enums\PromotionActorType;
use App\Enums\PromotionAttemptStatus;
use App\Enums\PromotionOperationKind;
use App\Models\ImportBatch;
use App\Models\ImportDecisionSnapshot;
use App\Models\ImportItem;
use App\Models\PromotionAttempt;
use App\Models\PromotionAttemptFailure;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceChecksumReservation;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class ImportDomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_import_relational_foundation_is_present(): void
    {
        foreach ([
            'import_batches',
            'import_items',
            'import_decision_snapshots',
            'promotion_attempts',
            'promotion_attempt_failures',
            'workspace_checksum_reservations',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}.");
        }

        $this->assertTrue(Schema::hasColumns('import_items', [
            'import_batch_id', 'workspace_id', 'staged_object_key', 'source_filename',
            'source_checksum_sha256', 'preflight_status', 'match_status',
            'current_decision_snapshot_id', 'replaced_by_import_item_id',
        ]));
        $this->assertTrue(Schema::hasColumns('promotion_attempts', [
            'decision_snapshot_id', 'attempt_ordinal', 'actor_identity',
            'operation_kind', 'client_idempotency_key', 'request_digest_sha256',
        ]));
    }

    public function test_snapshot_and_replacement_bindings_are_item_batch_and_workspace_scoped(): void
    {
        [$workspace, $actor, $batch] = $this->batch();
        $first = $this->item($batch);
        $second = $this->item($batch);
        $snapshot = $this->snapshot($first, $actor, ['title' => 'First']);
        $wrongSnapshot = $this->snapshot($second, $actor, ['title' => 'Second']);

        $first->update(['current_decision_snapshot_id' => $snapshot->id]);
        $this->assertTrue($first->fresh()->currentDecisionSnapshot->is($snapshot));

        try {
            DB::table('import_items')->where('id', $first->id)->update([
                'current_decision_snapshot_id' => $wrongSnapshot->id,
            ]);
            $this->fail('A snapshot belonging to another item was accepted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $first->update(['replaced_by_import_item_id' => $second->id]);
        $this->assertTrue($first->fresh()->replacement->is($second));

        [$otherWorkspace, , $otherBatch] = $this->batch();
        $otherItem = $this->item($otherBatch);
        $this->assertFalse($workspace->is($otherWorkspace));

        $this->expectException(QueryException::class);
        DB::table('import_items')->where('id', $second->id)->update([
            'replaced_by_import_item_id' => $otherItem->id,
        ]);
    }

    public function test_nullable_predecision_and_unreplaced_states_are_valid(): void
    {
        [, , $batch] = $this->batch();
        $item = $this->item($batch);

        $this->assertNull($item->current_decision_snapshot_id);
        $this->assertNull($item->replaced_by_import_item_id);
        $this->assertSame(ImportPreflightStatus::Pending, $item->preflight_status);
        $this->assertSame(ImportMatchStatus::Pending, $item->match_status);
    }

    public function test_cross_workspace_snapshot_and_self_replacement_are_rejected(): void
    {
        [, $actor, $batch] = $this->batch();
        $item = $this->item($batch);
        [, $otherActor, $otherBatch] = $this->batch();
        $otherItem = $this->item($otherBatch);
        $otherSnapshot = $this->snapshot($otherItem, $otherActor, ['title' => 'Other workspace']);

        try {
            DB::table('import_items')->where('id', $item->id)->update([
                'current_decision_snapshot_id' => $otherSnapshot->id,
            ]);
            $this->fail('A cross-workspace decision snapshot was accepted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $item->update(['replaced_by_import_item_id' => $item->id]);
    }

    public function test_promotion_identity_is_actor_scoped_generated_and_fail_closed(): void
    {
        [$workspace, $actor, $batch] = $this->batch();
        $item = $this->item($batch);
        $snapshot = $this->snapshot($item, $actor, ['title' => 'Medication policy']);
        $attempt = $this->attempt($workspace, $item, $snapshot, $actor);

        $this->assertSame('user:'.$actor->id, $attempt->fresh()->actor_identity);
        $this->assertSame(PromotionAttemptStatus::Reserved, $attempt->status);
        $this->assertFalse($attempt->status->isTerminal());

        $this->expectException(QueryException::class);
        $this->attempt($workspace, $item, $snapshot, $actor);
    }

    public function test_only_one_open_attempt_and_one_failure_per_lease_generation_are_allowed(): void
    {
        [$workspace, $actor, $batch] = $this->batch();
        $item = $this->item($batch);
        $snapshot = $this->snapshot($item, $actor, ['title' => 'Safeguarding']);
        $attempt = $this->attempt($workspace, $item, $snapshot, $actor);

        PromotionAttemptFailure::query()->create([
            'promotion_attempt_id' => $attempt->id,
            'lease_generation' => 1,
            'failure_code' => 'copy_failed',
            'safe_context' => ['retryable' => true],
        ]);

        try {
            PromotionAttemptFailure::query()->create([
                'promotion_attempt_id' => $attempt->id,
                'lease_generation' => 1,
                'failure_code' => 'copy_failed_again',
            ]);
            $this->fail('The same lease generation consumed failure authority twice.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        PromotionAttempt::query()->create([
            'public_id' => (string) Str::uuid(),
            'import_item_id' => $item->id,
            'workspace_id' => $workspace->id,
            'decision_snapshot_id' => $snapshot->id,
            'attempt_ordinal' => 2,
            'status' => PromotionAttemptStatus::Copying,
            'reserved_object_key' => 'documents/'.$item->public_id.'/'.str_repeat('b', 64),
            'actor_type' => PromotionActorType::Human,
            'actor_user_id' => $actor->id,
            'operation_kind' => PromotionOperationKind::Retry,
            'client_idempotency_key' => 'retry-key',
            'request_digest_sha256' => str_repeat('b', 64),
        ]);
    }

    public function test_decision_canonical_bytes_and_digest_are_deterministic(): void
    {
        $canonical = app(StructuredExtractionCanonicaliser::class);
        $left = $canonical->canonicalValueBytes([
            'title' => 'Medication policy',
            'tags' => ['current', 'medication'],
            'family_decision' => ['kind' => 'new_family'],
        ]);
        $right = $canonical->canonicalValueBytes([
            'family_decision' => ['kind' => 'new_family'],
            'tags' => ['current', 'medication'],
            'title' => 'Medication policy',
        ]);

        $this->assertSame($left, $right);
        $this->assertSame(hash('sha256', $left), hash('sha256', $right));
    }

    public function test_model_guards_preserve_verified_source_snapshot_and_reservation_identity(): void
    {
        [$workspace, $actor, $batch] = $this->batch();
        $item = $this->item($batch, [
            'source_filename' => 'source.pdf',
            'preflight_status' => ImportPreflightStatus::Verified,
            'source_checksum_sha256' => str_repeat('a', 64),
            'media_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
        $snapshot = $this->snapshot($item, $actor, ['title' => 'Immutable']);
        WorkspaceChecksumReservation::query()->create([
            'workspace_id' => $workspace->id,
            'source_checksum_sha256' => str_repeat('a', 64),
        ]);

        try {
            $item->update(['size_bytes' => 101]);
            $this->fail('Verified source identity changed.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        try {
            $item->update(['source_filename' => 'renamed.pdf']);
            $this->fail('Import source filename identity changed.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $snapshot->update(['digest_sha256' => str_repeat('b', 64)]);
    }

    /** @return array{Workspace, User, ImportBatch} */
    private function batch(): array
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->create();
        $batch = ImportBatch::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'initiated_by_user_id' => $actor->id,
            'status' => ImportBatchStatus::Open,
            'retention_expires_at' => now()->addDays(7),
        ]);

        return [$workspace, $actor, $batch];
    }

    /** @param array<string, mixed> $overrides */
    private function item(ImportBatch $batch, array $overrides = []): ImportItem
    {
        $publicId = (string) Str::uuid();

        return ImportItem::query()->create(array_merge([
            'public_id' => $publicId,
            'import_batch_id' => $batch->id,
            'workspace_id' => $batch->workspace_id,
            'staged_object_key' => 'imports/workspaces/'.$batch->workspace->public_id.'/items/'.$publicId.'/source',
            'preflight_status' => ImportPreflightStatus::Pending,
            'match_status' => ImportMatchStatus::Pending,
        ], $overrides));
    }

    /** @param array<string, mixed> $definition */
    private function snapshot(ImportItem $item, User $actor, array $definition): ImportDecisionSnapshot
    {
        $canonical = app(StructuredExtractionCanonicaliser::class)->canonicalValueBytes($definition);

        return ImportDecisionSnapshot::query()->create([
            'public_id' => (string) Str::uuid(),
            'import_item_id' => $item->id,
            'schema_version' => 1,
            'canonical_definition' => $canonical,
            'digest_sha256' => hash('sha256', $canonical),
            'actor_user_id' => $actor->id,
        ]);
    }

    private function attempt(
        Workspace $workspace,
        ImportItem $item,
        ImportDecisionSnapshot $snapshot,
        User $actor,
    ): PromotionAttempt {
        return PromotionAttempt::query()->create([
            'public_id' => (string) Str::uuid(),
            'import_item_id' => $item->id,
            'workspace_id' => $workspace->id,
            'decision_snapshot_id' => $snapshot->id,
            'attempt_ordinal' => 1,
            'status' => PromotionAttemptStatus::Reserved,
            'reserved_object_key' => 'documents/'.$item->public_id.'/'.str_repeat('a', 64),
            'actor_type' => PromotionActorType::Human,
            'actor_user_id' => $actor->id,
            'operation_kind' => PromotionOperationKind::Promote,
            'client_idempotency_key' => 'same-key',
            'request_digest_sha256' => str_repeat('a', 64),
        ]);
    }
}
