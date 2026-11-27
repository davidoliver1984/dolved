<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\CompleteDifyDocumentDeletion;
use App\Actions\Documents\RetryDocumentIngestion;
use App\Actions\Documents\SyncDifyDocument;
use App\Enums\ChecksumVerificationStatus;
use App\Jobs\SyncDifyDocument as SyncDifyDocumentJob;
use App\Models\DifyDocumentBinding;
use App\Models\DifyWorkspaceBinding;
use App\Models\Document;
use App\Models\DocumentDeletionOperation;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DifyDocumentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_current_document_is_disabled_until_indexed_and_labelled_then_enabled(): void
    {
        [$document, $workspaceBinding] = $this->eligibleDocument();
        $remoteDocumentId = (string) Str::uuid();
        $batchId = (string) Str::uuid();
        $requests = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$requests, $batchId, $remoteDocumentId) {
            $requests[] = $request;
            if ($request->method() === 'GET' && str_contains($request->url(), '/documents?')) {
                return Http::response(['data' => []]);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/document/create-by-file')) {
                return Http::response(['document' => ['id' => $remoteDocumentId], 'batch' => $batchId]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/indexing-status')) {
                return Http::response(['data' => [[
                    'id' => $remoteDocumentId,
                    'indexing_status' => 'completed',
                    'completed_segments' => 2,
                    'total_segments' => 2,
                ]]]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/segments?')) {
                return Http::response(['data' => [['id' => (string) Str::uuid()]], 'total' => 2]);
            }

            return Http::response(['result' => 'success']);
        });

        $this->assertTrue(app(SyncDifyDocument::class)->handle($document->id));

        $binding = DifyDocumentBinding::query()->sole();
        $this->assertSame('global', $binding->scope_key);
        $this->assertSame('available', $binding->sync_status);
        $this->assertSame($remoteDocumentId, $binding->remote_document_id);
        $this->assertSame($batchId, $binding->remote_batch_id);
        $this->assertSame($document->source_checksum_sha256, $binding->source_checksum_sha256);
        $this->assertNotNull($binding->synced_at);
        $this->assertSame(2, $binding->indexed_segment_count);
        $this->assertSame('available', $binding->segment_count_status);
        $this->assertNotNull($binding->segment_count_observed_at);

        $statusActions = collect($requests)
            ->filter(fn (Request $request): bool => $request->method() === 'PATCH')
            ->map(fn (Request $request): string => str_ends_with($request->url(), '/disable') ? 'disable' : 'enable')
            ->values()->all();
        $this->assertSame(['disable', 'enable'], $statusActions);
        $metadata = collect($requests)->first(fn (Request $request): bool => str_ends_with($request->url(), '/documents/metadata'));
        $this->assertInstanceOf(Request::class, $metadata);
        $this->assertSame($workspaceBinding->scope_metadata_id, $metadata->data()['operation_data'][0]['metadata_list'][0]['id']);
        $this->assertSame('global', $metadata->data()['operation_data'][0]['metadata_list'][0]['value']);
    }

    public function test_ineligible_document_is_disabled_without_upload_or_generation(): void
    {
        [$document, $workspaceBinding] = $this->eligibleDocument();
        $document->forceFill(['governance_status' => 'withdrawn', 'withdrawn_at' => now()])->save();
        $binding = DifyDocumentBinding::query()->create([
            'workspace_id' => $document->workspace_id,
            'document_id' => $document->id,
            'dataset_id' => $workspaceBinding->dataset_id,
            'scope_key' => 'global',
            'remote_document_id' => (string) Str::uuid(),
            'remote_batch_id' => (string) Str::uuid(),
            'remote_name' => 'dolved-existing.pdf',
            'sync_status' => 'available',
            'source_checksum_sha256' => $document->source_checksum_sha256,
            'synced_at' => now(),
        ]);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['result' => 'success']));

        $this->assertTrue(app(SyncDifyDocument::class)->handle($document->id));

        $this->assertSame('disabled', $binding->refresh()->sync_status);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/documents/status/disable'));
    }

    public function test_specific_document_gets_one_exactly_labelled_remote_copy_per_location(): void
    {
        [$document] = $this->eligibleDocument();
        $locations = OrganisationalLocation::factory()->count(2)->for($document->workspace)->create();
        DB::table('document_applicability_snapshots')->where('document_id', $document->id)->update([
            'scope' => 'specific',
        ]);
        $document->applicabilitySnapshot->locations()->attach(
            $locations->pluck('id')->all(),
            ['workspace_id' => $document->workspace_id],
        );

        $created = 0;
        $remoteIdsByBatch = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$created, &$remoteIdsByBatch) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/documents?')) {
                return Http::response(['data' => []]);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/document/create-by-file')) {
                $created++;
                $remoteId = (string) Str::uuid();
                $batchId = (string) Str::uuid();
                $remoteIdsByBatch[$batchId] = $remoteId;

                return Http::response(['document' => ['id' => $remoteId], 'batch' => $batchId]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/indexing-status')) {
                $batchId = collect(array_keys($remoteIdsByBatch))
                    ->first(fn (string $candidate): bool => str_contains($request->url(), $candidate));

                return Http::response(['data' => [[
                    'id' => $remoteIdsByBatch[$batchId],
                    'indexing_status' => 'completed',
                ]]]);
            }

            return Http::response(['result' => 'success']);
        });

        $this->assertTrue(app(SyncDifyDocument::class)->handle($document->id));

        $this->assertSame(
            $locations->pluck('public_id')->sort()->values()->all(),
            DifyDocumentBinding::query()->pluck('scope_key')->sort()->values()->all(),
        );
        $this->assertSame(2, DifyDocumentBinding::query()->where('sync_status', 'available')->count());
        Http::assertSentCount(14);
        foreach ($locations as $location) {
            Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/documents/metadata')
                && $request->data()['operation_data'][0]['metadata_list'][0]['value'] === $location->public_id);
        }
    }

    public function test_failed_dify_index_can_be_queued_once_for_supported_file_update(): void
    {
        Queue::fake();
        [$document, $workspaceBinding] = $this->eligibleDocument();
        config(['documents.processing_driver' => 'dify']);
        $document->forceFill([
            'status' => 'failed',
            'failure_category' => 'dify_indexing_error',
            'failure_message' => 'Indexing failed.',
        ])->save();
        $binding = DifyDocumentBinding::query()->create([
            'workspace_id' => $document->workspace_id,
            'document_id' => $document->id,
            'dataset_id' => $workspaceBinding->dataset_id,
            'scope_key' => 'global',
            'remote_document_id' => (string) Str::uuid(),
            'remote_batch_id' => (string) Str::uuid(),
            'remote_name' => 'dolved-existing.pdf',
            'sync_status' => 'failed',
            'failure_code' => 'dify_indexing_error',
            'source_checksum_sha256' => $document->source_checksum_sha256,
        ]);

        $key = (string) Str::uuid();
        $first = app(RetryDocumentIngestion::class)->handle(
            $document,
            $document->createdBy,
            $key,
            (string) Str::uuid(),
        );
        $second = app(RetryDocumentIngestion::class)->handle(
            $document,
            $document->createdBy,
            $key,
            (string) Str::uuid(),
        );

        $this->assertTrue($first->is($second));
        $this->assertSame('processing', $document->fresh()->status->value);
        $this->assertSame('retry_pending', $binding->fresh()->sync_status);
        $this->assertDatabaseCount('outbox_events', 0);
        Queue::assertPushed(SyncDifyDocumentJob::class, 1);
    }

    public function test_queued_dify_retry_uses_supported_file_update_and_restores_availability(): void
    {
        [$document, $workspaceBinding] = $this->eligibleDocument();
        config(['documents.processing_driver' => 'dify']);
        $document->forceFill(['status' => 'processing'])->save();
        $remoteId = (string) Str::uuid();
        $batchId = (string) Str::uuid();
        $remoteName = sprintf(
            'dolved-%s-%s-%s.pdf',
            $document->public_id,
            substr(hash('sha256', 'global'), 0, 12),
            substr((string) $document->source_checksum_sha256, 0, 12),
        );
        DifyDocumentBinding::query()->create([
            'workspace_id' => $document->workspace_id,
            'document_id' => $document->id,
            'dataset_id' => $workspaceBinding->dataset_id,
            'scope_key' => 'global',
            'remote_document_id' => $remoteId,
            'remote_batch_id' => (string) Str::uuid(),
            'remote_name' => $remoteName,
            'sync_status' => 'retry_pending',
            'source_checksum_sha256' => $document->source_checksum_sha256,
        ]);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($batchId, $remoteId) {
            if ($request->method() === 'PATCH' && str_ends_with($request->url(), '/'.$remoteId)) {
                return Http::response(['document' => ['id' => $remoteId], 'batch' => $batchId]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/indexing-status')) {
                return Http::response(['data' => [[
                    'id' => $remoteId,
                    'indexing_status' => 'completed',
                ]]]);
            }

            return Http::response(['result' => 'success']);
        });

        $this->assertTrue(app(SyncDifyDocument::class)->handle($document->id));

        $this->assertSame('indexed', $document->fresh()->status->value);
        $this->assertSame('available', $document->difyBindings()->sole()->sync_status);
        Http::assertSentCount(6);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/'.$remoteId));
    }

    public function test_dify_deletion_disables_and_removes_remote_copy_before_authoritative_original(): void
    {
        [$document, $workspaceBinding] = $this->eligibleDocument();
        config(['documents.processing_driver' => 'dify']);
        $remoteId = (string) Str::uuid();
        $binding = DifyDocumentBinding::query()->create([
            'workspace_id' => $document->workspace_id,
            'document_id' => $document->id,
            'dataset_id' => $workspaceBinding->dataset_id,
            'scope_key' => 'global',
            'remote_document_id' => $remoteId,
            'remote_batch_id' => (string) Str::uuid(),
            'remote_name' => 'dolved-existing.pdf',
            'sync_status' => 'available',
            'source_checksum_sha256' => $document->source_checksum_sha256,
        ]);
        $operation = DocumentDeletionOperation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $document->workspace_id,
            'document_id' => $document->id,
            'requested_by_user_id' => $document->created_by_user_id,
            'correlation_id' => (string) Str::uuid(),
            'status' => 'awaiting_quiescence',
            'active_attempt_ids' => [],
        ]);
        $document->forceFill(['status' => 'deleting'])->save();
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response('', 204));

        $this->assertTrue(app(CompleteDifyDocumentDeletion::class)->handle($operation->id));

        $this->assertSame('deleted', $document->fresh()->status->value);
        $this->assertSame('completed', $operation->fresh()->status->value);
        $this->assertSame('deleted', $binding->fresh()->sync_status);
        $this->assertFalse(Storage::disk('dify-sync-test')->exists($document->storage_key));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/'.$remoteId));
    }

    /** @return array{Document, DifyWorkspaceBinding} */
    private function eligibleDocument(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($user)->create();
        $workspaceBinding = DifyWorkspaceBinding::query()->create([
            'workspace_id' => $workspace->id,
            'app_id' => (string) Str::uuid(),
            'dataset_id' => (string) Str::uuid(),
            'scope_metadata_id' => (string) Str::uuid(),
            'enabled' => true,
        ]);
        $checksum = hash('sha256', 'verified original bytes');
        $document = Document::factory()->for($workspace)->for($user, 'createdBy')->indexed()->approved()->create([
            'source_filename' => 'policy.pdf',
            'media_type' => 'application/pdf',
            'size_bytes' => strlen('verified original bytes'),
            'source_checksum_sha256' => $checksum,
            'checksum_verification_status' => ChecksumVerificationStatus::Verified,
        ]);
        $storageRoot = sys_get_temp_dir().'/dolved-dify-sync-test-'.Str::uuid();
        config([
            'filesystems.disks.dify-sync-test' => ['driver' => 'local', 'root' => $storageRoot, 'throw' => true],
            'documents.storage_disk' => 'dify-sync-test',
            'dify.base_url' => 'http://dify.test',
            'dify.knowledge_api_key' => 'server-knowledge-secret',
        ]);
        Storage::disk('dify-sync-test')->put($document->storage_key, 'verified original bytes');

        return [$document->fresh(['family.documents', 'applicabilitySnapshot.locations']), $workspaceBinding];
    }
}
