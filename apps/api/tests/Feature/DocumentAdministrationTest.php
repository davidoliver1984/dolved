<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\AdvanceDocumentDeletion;
use App\Actions\Documents\ClaimDocumentDeletion;
use App\Actions\Documents\CompleteDocumentDeletion;
use App\Actions\Ingestion\CancelIngestionAttempt;
use App\Actions\Ingestion\PublishIngestionOutbox;
use App\Contracts\Ingestion\IngestionEventPublisher;
use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionProjectionStatus;
use App\Enums\ExtractionUploadStatus;
use App\Enums\IngestionAttemptStatus;
use App\Jobs\AdvanceDocumentDeletion as AdvanceDocumentDeletionJob;
use App\Models\Document;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionProjectionElement;
use App\Models\DocumentExtractionProjectionGeneration;
use App\Models\DocumentExtractionProjectionWarning;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\DocumentIngestionRetry;
use App\Models\IngestionEventClaim;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DocumentAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_members_can_list_only_their_workspace_documents(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->member()->create();
        $owned = Document::factory()->for($workspace)->create(['source_filename' => 'Needle policy.pdf']);
        Document::factory()->create(['source_filename' => 'Other tenant.pdf']);

        $response = $this->actingAs($user)->getJson(
            "/api/workspaces/{$workspace->public_id}/documents?search=needle",
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $owned->public_id)
            ->assertJsonPath('data.0.capabilities.retry', false)
            ->assertJsonMissing(['storage_key' => $owned->storage_key]);
    }

    public function test_cross_workspace_document_detail_fails_closed(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->member()->create();
        $document = Document::factory()->for($other)->create();

        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}")
            ->assertNotFound();
    }

    public function test_document_detail_conceals_deleted_sources_but_keeps_live_sources_available(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->member()->create();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $path = "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}";

        $this->actingAs($user)->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.public_id', $document->public_id)
            ->assertJsonMissing(['storage_key' => $document->storage_key]);

        $document->forceFill(['status' => DocumentStatus::Deleted])->save();
        $this->actingAs($user)->getJson($path)->assertNotFound();
    }

    public function test_retry_is_owner_only_and_idempotent_per_document_key(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($owner)->owner()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $document = Document::factory()->for($workspace)->failed()->create();
        $key = (string) Str::uuid();
        $path = "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}/retries";

        $this->actingAs($member)->postJson($path, ['idempotency_key' => $key])->assertForbidden();
        $this->actingAs($owner)->postJson($path, ['idempotency_key' => $key])->assertAccepted();
        $this->actingAs($owner)->postJson($path, ['idempotency_key' => $key])->assertAccepted();

        $this->assertSame(DocumentStatus::Queued, $document->fresh()->status);
        $this->assertSame(1, DocumentIngestionRetry::query()->count());
        $this->assertSame(1, OutboxEvent::query()->count());
    }

    public function test_delete_is_owner_only_single_operation_and_waits_for_quiescence(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($owner)->owner()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $path = "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}";

        $this->actingAs($member)->deleteJson($path)->assertForbidden();
        $first = $this->actingAs($owner)->deleteJson($path)->assertAccepted()->json('data.operation.public_id');
        $second = $this->actingAs($owner)->deleteJson($path)->assertAccepted()->json('data.operation.public_id');

        $this->assertSame($first, $second);
        $this->assertSame(DocumentStatus::Deleting, $document->fresh()->status);
        $this->assertSame(DocumentDeletionStatus::AwaitingQuiescence, $document->deletionOperation->status);
        Queue::assertPushed(AdvanceDocumentDeletionJob::class, 2);
    }

    public function test_quiescent_deletion_dispatches_one_immutable_event(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($owner)->owner()->create();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $this->actingAs($owner)->deleteJson(
            "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}",
        )->assertAccepted();
        $operation = $document->deletionOperation;

        $this->assertTrue(app(AdvanceDocumentDeletion::class)->handle($operation->id));
        $this->assertTrue(app(AdvanceDocumentDeletion::class)->handle($operation->id));
        $this->assertSame(DocumentDeletionStatus::Queued, $operation->fresh()->status);
        $this->assertSame(1, OutboxEvent::query()->where('event_id', $operation->public_id)->count());

        $transport = Mockery::mock(IngestionEventPublisher::class);
        $transport->shouldReceive('publish')->once()->andReturn('deletion-message-id');
        $this->app->instance(IngestionEventPublisher::class, $transport);
        $this->assertSame(1, app(PublishIngestionOutbox::class)->handle()['published']);
    }

    public function test_deletion_waits_for_snapshotted_attempt_then_emits_its_exact_vector_scope(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($owner)->owner()->create();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $generation = WorkspaceCorpusGeneration::factory()->for($workspace)->create();
        $token = (string) Str::uuid();
        $attempt = IngestionEventClaim::factory()->for($document)->create([
            'workspace_id' => $workspace->id,
            'workspace_public_id' => $workspace->public_id,
            'document_public_id' => $document->public_id,
            'embedding_space_generation_id' => $generation->embedding_space_generation_id,
            'workspace_corpus_generation_id' => $generation->id,
            'status' => 'open',
            'lease_token_hash' => hash('sha256', $token),
            'lease_expires_at' => now()->addMinute(),
        ]);

        $this->actingAs($owner)->deleteJson(
            "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}",
        )->assertAccepted();
        $operation = $document->deletionOperation;

        $this->assertSame([$attempt->id], $operation->active_attempt_ids);
        $this->assertFalse(app(AdvanceDocumentDeletion::class)->handle($operation->id));
        $this->assertDatabaseCount('outbox_events', 0);

        app(CancelIngestionAttempt::class)->handle($attempt->event_id, [
            'event_id' => $attempt->event_id,
            'workspace_id' => $workspace->public_id,
            'document_id' => $document->public_id,
            'lease_token' => $token,
        ]);

        $this->assertTrue(app(AdvanceDocumentDeletion::class)->handle($operation->id));
        $scope = OutboxEvent::query()->where('event_id', $operation->public_id)->firstOrFail()->payload['vector_scopes'][0];
        $this->assertSame($workspace->public_id, $scope['workspace_id']);
        $this->assertSame($document->public_id, $scope['document_id']);
        $this->assertSame($generation->public_id, $scope['workspace_corpus_generation_id']);
        $this->assertSame(
            $generation->embeddingSpaceGeneration->public_id,
            $scope['vector_space']['embedding_space_generation_id'],
        );
    }

    public function test_deletion_cancellation_is_terminal_without_becoming_ingestion_failure(): void
    {
        $document = Document::factory()->deleting()->create();
        $token = (string) Str::uuid();
        $attempt = IngestionEventClaim::factory()->for($document)->create([
            'workspace_id' => $document->workspace_id,
            'workspace_public_id' => $document->workspace->public_id,
            'document_public_id' => $document->public_id,
            'status' => 'open',
            'lease_token_hash' => hash('sha256', $token),
            'lease_expires_at' => now()->addMinute(),
        ]);

        app(CancelIngestionAttempt::class)->handle($attempt->event_id, [
            'event_id' => $attempt->event_id,
            'workspace_id' => $document->workspace->public_id,
            'document_id' => $document->public_id,
            'lease_token' => $token,
        ]);

        $this->assertSame('cancelled', $attempt->fresh()->status->value);
        $this->assertSame(DocumentStatus::Deleting, $document->fresh()->status);
        $this->assertNull($attempt->fresh()->failure_code);
    }

    public function test_verified_zero_scope_completion_removes_source_and_marks_deleted(): void
    {
        Queue::fake();
        Storage::fake('document-administration-test');
        config()->set('documents.storage_disk', 'document-administration-test');
        config()->set('ingestion.orchestration.extraction_artifact_disk', 'document-administration-test');
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($owner)->owner()->create();
        $document = Document::factory()->for($workspace)->indexed()->create();
        Storage::disk('document-administration-test')->put($document->storage_key, 'content');
        $attempt = IngestionEventClaim::factory()->for($document)->create([
            'status' => IngestionAttemptStatus::Completed,
            'completed_at' => now(),
        ]);
        $artifactKey = $document->storage_key.'.extraction.json';
        Storage::disk('document-administration-test')->put($artifactKey, '{}');
        $upload = DocumentExtractionUploadAuthorisation::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id,
            'document_id' => $document->id, 'ingestion_event_claim_id' => $attempt->id,
            'object_key' => $artifactKey, 'lease_generation' => 1, 'expires_at' => now()->addMinute(),
            'status' => ExtractionUploadStatus::Verified, 'artifact_sha256' => str_repeat('a', 64),
            'size_bytes' => 2, 'projection_manifest_digest' => str_repeat('b', 64),
            'warning_manifest_digest' => str_repeat('c', 64), 'element_count' => 1,
            'warning_count' => 1, 'verified_at' => now(),
        ]);
        $artifact = DocumentExtractionArtifact::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id,
            'document_id' => $document->id, 'upload_authorisation_id' => $upload->id,
            'object_key' => $artifactKey, 'contract_version' => 'document-extraction-artifact-v1',
            'artifact_sha256' => str_repeat('a', 64), 'size_bytes' => 2,
            'projection_manifest_digest' => str_repeat('b', 64),
            'warning_manifest_digest' => str_repeat('c', 64), 'element_count' => 1,
            'warning_count' => 1, 'verified_at' => now(), 'published_at' => now(),
        ]);
        $generation = DocumentExtractionProjectionGeneration::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id,
            'document_id' => $document->id, 'document_extraction_artifact_id' => $artifact->id,
            'status' => ExtractionProjectionStatus::Published, 'expected_element_count' => 1,
            'expected_warning_count' => 1, 'expected_projection_manifest_digest' => str_repeat('b', 64),
            'expected_warning_manifest_digest' => str_repeat('c', 64),
            'verified_projection_manifest_digest' => str_repeat('b', 64),
            'verified_warning_manifest_digest' => str_repeat('c', 64),
            'verified_at' => now(), 'published_at' => now(),
        ]);
        DocumentExtractionProjectionElement::query()->create([
            'projection_generation_id' => $generation->id, 'workspace_id' => $workspace->id,
            'document_id' => $document->id, 'element_id' => (string) Str::uuid(), 'ordinal' => 0,
            'kind' => 'paragraph', 'text' => 'content', 'payload' => ['text' => 'content'],
        ]);
        DocumentExtractionProjectionWarning::query()->create([
            'projection_generation_id' => $generation->id, 'ordinal' => 0,
            'payload' => ['code' => 'fixture', 'message' => 'fixture'],
        ]);
        $document->forceFill(['active_extraction_projection_generation_id' => $generation->id])->save();
        $this->actingAs($owner)->deleteJson(
            "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}",
        )->assertAccepted();
        $operation = $document->deletionOperation;
        app(AdvanceDocumentDeletion::class)->handle($operation->id);
        $event = OutboxEvent::query()->where('event_id', $operation->public_id)->firstOrFail()->payload;
        $grant = app(ClaimDocumentDeletion::class)->handle($event, hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)));

        app(CompleteDocumentDeletion::class)->handle($operation->public_id, [
            'event_id' => $operation->public_id,
            'workspace_id' => $workspace->public_id,
            'document_id' => $document->public_id,
            'lease_token' => $grant['lease_token'],
            'scopes' => [],
        ]);

        Storage::disk('document-administration-test')->assertMissing($document->storage_key);
        Storage::disk('document-administration-test')->assertMissing($artifactKey);
        $this->assertDatabaseMissing('document_extraction_artifacts', ['id' => $artifact->id]);
        $this->assertDatabaseMissing('document_extraction_projection_generations', ['id' => $generation->id]);
        $this->assertDatabaseMissing('document_extraction_projection_elements', ['projection_generation_id' => $generation->id]);
        $this->assertDatabaseMissing('document_extraction_projection_warnings', ['projection_generation_id' => $generation->id]);
        $this->assertSame(DocumentStatus::Deleted, $document->fresh()->status);
        $this->assertSame(DocumentDeletionStatus::Completed, $operation->fresh()->status);
    }
}
