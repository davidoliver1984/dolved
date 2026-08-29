<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ingestion\AcknowledgeExtractionArtifactUpload;
use App\Actions\Ingestion\AuthoriseExtractionArtifactUpload;
use App\Actions\Ingestion\ClaimDocumentIngestion;
use App\Actions\Ingestion\SweepExtractionArtifactOrphans;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\Document;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\ExtractionArtifactObjectStorage;
use App\Support\Ingestion\DocumentIngestionRequestedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ExtractionArtifactUploadTest extends TestCase
{
    use RefreshDatabase;

    private IngestionEventClaim $attempt;

    /** @var array<string, mixed> */
    private array $context;

    private ExtractionArtifactObjectStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-29T12:00:00Z');
        $profile = EmbeddingProfile::factory()->voyageV1()->create();
        EmbeddingSpaceGeneration::factory()->for($profile)->available()->create([
            'dimensions' => 1024,
        ]);
        $document = Document::factory()->for(Workspace::factory(), 'workspace')
            ->for(User::factory(), 'createdBy')->create(['status' => DocumentStatus::Queued]);
        $event = app(DocumentIngestionRequestedPayload::class)->build(
            $document->load('workspace'), (string) Str::uuid(), (string) Str::uuid(), CarbonImmutable::parse('2026-08-29T12:00:00Z'),
        );
        $grant = app(ClaimDocumentIngestion::class)->handle(
            $event,
            hash('sha256', json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        );
        $this->attempt = IngestionEventClaim::query()->sole();
        $this->context = [
            'contract_version' => 1,
            'event_id' => $this->attempt->event_id,
            'workspace_id' => $this->attempt->workspace_public_id,
            'document_id' => $this->attempt->document_public_id,
            'lease_token' => $grant->leaseToken,
            'lease_generation' => $grant->leaseGeneration,
        ];
        $this->storage = Mockery::mock(ExtractionArtifactObjectStorage::class);
        $this->app->instance(ExtractionArtifactObjectStorage::class, $this->storage);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_current_lease_authorises_exact_conditional_key_and_acknowledges_observed_identity(): void
    {
        $this->storage->shouldReceive('createUploadRequest')->once()
            ->andReturn([
                'url' => 'https://objects.example/exact', 'method' => 'PUT',
                'headers' => ['If-None-Match' => '*', 'Content-Type' => 'application/json'],
                'expires_at' => '2026-08-29T12:05:00+00:00',
            ]);
        $grant = app(AuthoriseExtractionArtifactUpload::class)->handle($this->attempt->event_id, $this->context);
        $this->assertSame('*', $grant['upload']['headers']['If-None-Match']);
        $this->assertStringContainsString($grant['authorisation_id'], $grant['object_key']);

        $sha = str_repeat('a', 64);
        $this->storage->shouldReceive('inspect')->once()->with($grant['object_key'])
            ->andReturn([
                'size_bytes' => 123,
                'sha256' => $sha,
                'contract_version' => 'document-extraction-artifact-v1',
            ]);
        $payload = [...$this->context,
            'authorisation_id' => $grant['authorisation_id'],
            'artifact_contract_version' => 'document-extraction-artifact-v1',
            'artifact_sha256' => $sha,
            'size_bytes' => 123,
            'projection_manifest_digest' => str_repeat('b', 64),
            'warning_manifest_digest' => str_repeat('c', 64),
            'element_count' => 2,
            'warning_count' => 1,
        ];
        $artifact = app(AcknowledgeExtractionArtifactUpload::class)->handle($this->attempt->event_id, $payload);
        $duplicate = app(AcknowledgeExtractionArtifactUpload::class)->handle($this->attempt->event_id, $payload);

        $this->assertSame($artifact->id, $duplicate->id);
        $this->assertSame($sha, $artifact->artifact_sha256);
        $this->assertDatabaseCount('document_extraction_artifacts', 1);
        $this->assertSame(ExtractionUploadStatus::Verified, DocumentExtractionUploadAuthorisation::query()->sole()->status);

        try {
            app(AcknowledgeExtractionArtifactUpload::class)->handle($this->attempt->event_id, [
                ...$payload,
                'artifact_sha256' => str_repeat('d', 64),
            ]);
            $this->fail('A conflicting duplicate acknowledgement was accepted.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('artifact_acknowledgement_conflict', $exception->errorCode);
        }
    }

    public function test_stale_generation_and_conflicting_acknowledgement_fail_closed(): void
    {
        $this->storage->shouldReceive('createUploadRequest')->once()->andReturn([
            'url' => 'https://objects.example/exact', 'method' => 'PUT', 'headers' => [],
            'expires_at' => '2026-08-29T12:05:00+00:00',
        ]);
        $grant = app(AuthoriseExtractionArtifactUpload::class)->handle($this->attempt->event_id, $this->context);
        $this->attempt->forceFill(['lease_generation' => 2])->save();

        $this->expectException(IngestionAttemptException::class);
        app(AcknowledgeExtractionArtifactUpload::class)->handle($this->attempt->event_id, [
            ...$this->context,
            'authorisation_id' => $grant['authorisation_id'],
            'artifact_contract_version' => 'document-extraction-artifact-v1',
            'artifact_sha256' => str_repeat('a', 64), 'size_bytes' => 1,
            'projection_manifest_digest' => str_repeat('b', 64),
            'warning_manifest_digest' => str_repeat('c', 64),
            'element_count' => 0, 'warning_count' => 0,
        ]);
    }

    public function test_orphan_sweep_waits_for_live_lease_then_deletes_only_exact_expired_key(): void
    {
        $record = DocumentExtractionUploadAuthorisation::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $this->attempt->workspace_id,
            'document_id' => $this->attempt->document_id, 'ingestion_event_claim_id' => $this->attempt->id,
            'object_key' => 'exact/orphan.json', 'lease_generation' => $this->attempt->lease_generation,
            'expires_at' => now()->subSecond(),
        ]);
        $this->storage->shouldReceive('deleteExact')->once()->with('exact/orphan.json');
        $this->assertSame(0, app(SweepExtractionArtifactOrphans::class)->handle()['claimed']);

        $this->attempt->forceFill(['lease_expires_at' => now()->subSecond()])->save();
        $result = app(SweepExtractionArtifactOrphans::class)->handle();

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(ExtractionUploadCleanupState::Deleted, $record->refresh()->cleanup_state);
    }

    public function test_orphan_sweep_never_deletes_a_published_artifact_key(): void
    {
        $record = DocumentExtractionUploadAuthorisation::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $this->attempt->workspace_id,
            'document_id' => $this->attempt->document_id, 'ingestion_event_claim_id' => $this->attempt->id,
            'object_key' => 'exact/published.json', 'lease_generation' => $this->attempt->lease_generation,
            'expires_at' => now()->subSecond(), 'status' => ExtractionUploadStatus::Failed,
            'cleanup_state' => ExtractionUploadCleanupState::Eligible,
        ]);
        DocumentExtractionArtifact::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $record->workspace_id,
            'document_id' => $record->document_id, 'upload_authorisation_id' => $record->id,
            'object_key' => $record->object_key, 'contract_version' => 'document-extraction-artifact-v1',
            'artifact_sha256' => str_repeat('a', 64), 'size_bytes' => 1,
            'projection_manifest_digest' => str_repeat('b', 64),
            'warning_manifest_digest' => str_repeat('c', 64), 'element_count' => 0,
            'warning_count' => 0, 'verified_at' => now(), 'published_at' => now(),
        ]);
        $this->attempt->forceFill(['lease_expires_at' => now()->subSecond()])->save();
        $this->storage->shouldReceive('deleteExact')->never();

        $result = app(SweepExtractionArtifactOrphans::class)->handle();

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(ExtractionUploadCleanupState::NotNeeded, $record->refresh()->cleanup_state);
    }

    public function test_orphan_sweep_persists_bounded_failure_and_exposes_exhaustion(): void
    {
        config()->set('ingestion.orchestration.extraction_cleanup_max_attempts', 2);
        $record = DocumentExtractionUploadAuthorisation::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $this->attempt->workspace_id,
            'document_id' => $this->attempt->document_id, 'ingestion_event_claim_id' => $this->attempt->id,
            'object_key' => 'exact/delete-failure.json', 'lease_generation' => $this->attempt->lease_generation,
            'expires_at' => now()->subSecond(), 'status' => ExtractionUploadStatus::Failed,
            'cleanup_state' => ExtractionUploadCleanupState::Eligible,
        ]);
        $this->attempt->forceFill(['lease_expires_at' => now()->subSecond()])->save();
        $this->storage->shouldReceive('deleteExact')->twice()->andThrow(
            IngestionAttemptException::invalid('artifact_storage_unavailable', 'Unavailable.', 503),
        );

        $this->assertSame(1, app(SweepExtractionArtifactOrphans::class)->handle()['failed']);
        $this->assertSame(ExtractionUploadCleanupState::Eligible, $record->refresh()->cleanup_state);
        $this->assertSame(1, $record->cleanup_attempt_count);

        $this->assertSame(1, app(SweepExtractionArtifactOrphans::class)->handle()['failed']);
        $this->assertSame(ExtractionUploadCleanupState::Failed, $record->refresh()->cleanup_state);
        $this->assertSame(2, $record->cleanup_attempt_count);
        $this->assertSame('artifact_delete_failed', $record->cleanup_error_code);
    }
}
