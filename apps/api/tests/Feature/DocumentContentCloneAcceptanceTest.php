<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\CreateApplicabilityOnlySuccessor;
use App\Actions\Documents\MaterialiseDocumentContentClone;
use App\Contracts\Ingestion\ContentCloneVectorGateway;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\ContentCloneManifestStatus;
use App\Enums\DocumentContentCloneStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use App\Enums\IngestionAttemptOrigin;
use App\Enums\IngestionAttemptStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentContentCloneManifest;
use App\Models\DocumentContentCloneOperation;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\DocumentFamily;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use App\Support\Documents\ContentCloneCompatibility;
use App\Support\Ingestion\MaterialisationPipelineIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentContentCloneAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-29T12:00:00Z');
        Storage::fake('content-clone-acceptance');
        config()->set('documents.storage_disk', 'content-clone-acceptance');
        config()->set('ingestion.orchestration.extraction_artifact_disk', 'content-clone-acceptance');
        config()->set('ingestion.orchestration.content_clone_manifest_disk', 'content-clone-acceptance');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_clone_publishes_all_layers_atomically_with_target_owned_lineage(): void
    {
        [$owner, $source] = $this->compatibleSource();
        $gateway = new CloneVectorGatewayFixture;
        $this->app->instance(ContentCloneVectorGateway::class, $gateway);

        [$target, $operation, $leaseToken] = app(CreateApplicabilityOnlySuccessor::class)->prepare(
            $source,
            $owner,
            now()->addMonth(),
            [],
            (string) Str::uuid(),
        );
        $this->assertInstanceOf(DocumentContentCloneOperation::class, $operation);
        $this->assertIsString($leaseToken);
        $this->assertSame(DocumentStatus::Processing, $target->status);
        $this->assertSame(IngestionAttemptOrigin::ContentClone, $operation->targetAttempt->attempt_origin);

        $result = app(MaterialiseDocumentContentClone::class)->handle($operation, $leaseToken);

        $operation->refresh();
        $targetAttempt = $operation->targetAttempt->refresh();
        $manifest = $operation->manifests()->sole();
        $targetChunk = DocumentChunk::query()->where('document_id', $result->id)->sole();
        $this->assertSame(DocumentStatus::Indexed, $result->status);
        $this->assertSame(DocumentContentCloneStatus::Indexed, $operation->status);
        $this->assertSame(IngestionAttemptStatus::Completed, $targetAttempt->status);
        $this->assertSame(ContentCloneManifestStatus::Consumed, $manifest->status);
        $this->assertSame(ExtractionUploadCleanupState::Eligible, $manifest->cleanup_state);
        $this->assertSame($targetAttempt->id, $targetChunk->ingestion_event_claim_id);
        $this->assertSame('content_clone', $targetAttempt->publication_evidence['attempt_origin']);
        $this->assertNotSame($operation->sourceAttempt->event_id, $targetAttempt->event_id);
        $this->assertSame(0, $targetAttempt->publication_evidence['provider_calls']);
        $this->assertNotNull($result->active_extraction_projection_generation_id);
        $this->assertDatabaseHas('workspace_corpus_generation_chunks', [
            'workspace_corpus_generation_id' => $targetAttempt->workspace_corpus_generation_id,
            'document_chunk_id' => $targetChunk->id,
        ]);
        $this->assertDatabaseHas('workspace_checksum_reservations', [
            'workspace_id' => $operation->workspace_id,
            'source_checksum_sha256' => $operation->source_checksum_sha256,
        ]);
        $this->assertSame(1, $gateway->cloneCalls);
    }

    public function test_mid_sequence_vector_failure_never_indexes_and_cleanup_precedes_fallback(): void
    {
        [$owner, $source] = $this->compatibleSource();
        $gateway = new CloneVectorGatewayFixture(mismatch: true);
        $this->app->instance(ContentCloneVectorGateway::class, $gateway);
        [$target, $operation, $leaseToken] = app(CreateApplicabilityOnlySuccessor::class)->prepare(
            $source,
            $owner,
            now()->addMonth(),
            [],
            (string) Str::uuid(),
        );

        try {
            app(MaterialiseDocumentContentClone::class)->handle($operation, $leaseToken);
            $this->fail('Mismatched vector completeness evidence was accepted.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('clone_vector_evidence_mismatch', $exception->errorCode);
        }

        $this->assertSame(DocumentContentCloneStatus::CleanupRequired, $operation->refresh()->status);
        $this->assertSame(DocumentStatus::Processing, $target->refresh()->status);
        $this->assertSame(IngestionAttemptStatus::Open, $operation->targetAttempt->refresh()->status);
        $this->assertDatabaseCount('document_chunks', 2);
        $targetChunkId = DocumentChunk::query()->where('document_id', $target->id)->valueOrFail('id');

        $fallback = app(MaterialiseDocumentContentClone::class)->cleanupForFallback($operation);

        $this->assertSame(DocumentStatus::Uploaded, $fallback->status);
        $this->assertSame(DocumentContentCloneStatus::FallbackReady, $operation->refresh()->status);
        $this->assertSame(IngestionAttemptStatus::Failed, $operation->targetAttempt->refresh()->status);
        $this->assertDatabaseMissing('document_chunks', ['document_id' => $target->id]);
        $this->assertDatabaseMissing('document_extraction_artifacts', ['document_id' => $target->id]);
        $this->assertDatabaseMissing('workspace_corpus_generation_chunks', [
            'document_chunk_id' => $targetChunkId,
        ]);
        $this->assertNull($target->refresh()->active_extraction_projection_generation_id);
        $this->assertTrue($gateway->cleanupCalled);
        foreach ($operation->manifests as $manifest) {
            $this->assertSame(ExtractionUploadCleanupState::Deleted, $manifest->refresh()->cleanup_state);
            Storage::disk('content-clone-acceptance')->assertMissing($manifest->object_key);
        }
    }

    public function test_clone_compatibility_rejects_pipeline_drift_and_non_active_generation(): void
    {
        [, $source, $attempt, $corpus] = $this->compatibleSource();
        $compatibility = app(ContentCloneCompatibility::class);
        $this->assertSame($attempt->id, $compatibility->sourceAttempt($source)?->id);

        DB::table('ingestion_event_claims')->where('id', $attempt->id)->update([
            'materialisation_pipeline_fingerprint' => str_repeat('f', 64),
        ]);
        $attempt->refresh();
        $this->assertNull($compatibility->sourceAttempt($source));

        $identity = app(MaterialisationPipelineIdentity::class)->for(
            $attempt->embeddingSpaceGeneration,
            $attempt->workspaceCorpusGeneration,
        );
        DB::table('ingestion_event_claims')->where('id', $attempt->id)->update([
            'materialisation_pipeline_fingerprint' => $identity['fingerprint'],
            'materialisation_pipeline_components' => json_encode($identity['components'], JSON_THROW_ON_ERROR),
        ]);
        $attempt->refresh();
        $corpus->forceFill([
            'status' => WorkspaceCorpusGenerationStatus::Superseded,
            'superseded_at' => now(),
        ])->save();

        $this->assertNull($compatibility->sourceAttempt($source));
    }

    public function test_clone_materialiser_rejects_an_ordinary_ingestion_attempt(): void
    {
        [$owner, $source] = $this->compatibleSource();
        $this->app->instance(ContentCloneVectorGateway::class, new CloneVectorGatewayFixture);
        [, $operation, $leaseToken] = app(CreateApplicabilityOnlySuccessor::class)->prepare(
            $source,
            $owner,
            now()->addMonth(),
            [],
            (string) Str::uuid(),
        );
        DB::table('ingestion_event_claims')->where('id', $operation->target_ingestion_event_claim_id)->update([
            'attempt_origin' => IngestionAttemptOrigin::Ingestion->value,
        ]);
        $operation->unsetRelation('targetAttempt');

        try {
            app(MaterialiseDocumentContentClone::class)->handle($operation, $leaseToken);
            $this->fail('The clone materialiser accepted an ordinary ingestion attempt.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('clone_lease_invalid', $exception->errorCode);
        }

        $this->assertSame(DocumentContentCloneStatus::Authorised, $operation->refresh()->status);
        $this->assertSame(IngestionAttemptOrigin::Ingestion, $operation->targetAttempt->attempt_origin);
    }

    public function test_clone_completion_rechecks_the_verified_live_source_under_the_checksum_lock(): void
    {
        [$owner, $source] = $this->compatibleSource();
        $this->app->instance(ContentCloneVectorGateway::class, new CloneVectorGatewayFixture);
        [, $operation, $leaseToken] = app(CreateApplicabilityOnlySuccessor::class)->prepare(
            $source,
            $owner,
            now()->addMonth(),
            [],
            (string) Str::uuid(),
        );
        $source->forceFill(['status' => DocumentStatus::Deleted])->save();

        try {
            app(MaterialiseDocumentContentClone::class)->handle($operation, $leaseToken);
            $this->fail('Clone completion accepted a source that was no longer live.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('clone_source_changed', $exception->errorCode);
        }

        $this->assertSame(DocumentContentCloneStatus::CleanupRequired, $operation->refresh()->status);
        $this->assertDatabaseMissing('workspace_checksum_reservations', [
            'workspace_id' => $operation->workspace_id,
            'source_checksum_sha256' => $operation->source_checksum_sha256,
        ]);
    }

    /** @return array{User, Document, IngestionEventClaim, WorkspaceCorpusGeneration} */
    private function compatibleSource(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $sourceBytes = 'verified source content';
        $source = Document::factory()->indexed()->approved()
            ->for($workspace)->for($family, 'family')->for($owner, 'createdBy')->create([
                'source_checksum_sha256' => hash('sha256', $sourceBytes),
                'checksum_verification_status' => ChecksumVerificationStatus::Verified,
                'size_bytes' => strlen($sourceBytes),
            ]);
        Storage::disk('content-clone-acceptance')->put($source->storage_key, $sourceBytes);

        $profile = EmbeddingProfile::factory()->voyageV1()->create();
        $embedding = EmbeddingSpaceGeneration::factory()->for($profile)->available()->create([
            'dimensions' => 1024,
        ]);
        $corpus = WorkspaceCorpusGeneration::factory()->for($workspace)->active()->create([
            'embedding_space_generation_id' => $embedding->id,
        ]);
        $workspace->forceFill(['active_workspace_corpus_generation_id' => $corpus->id])->save();
        $identity = app(MaterialisationPipelineIdentity::class)->for($embedding, $corpus);
        $attempt = IngestionEventClaim::factory()->for($source)->create([
            'workspace_id' => $workspace->id,
            'attempt_origin' => IngestionAttemptOrigin::Ingestion,
            'status' => IngestionAttemptStatus::Completed,
            'embedding_space_generation_id' => $embedding->id,
            'workspace_corpus_generation_id' => $corpus->id,
            'materialisation_pipeline_fingerprint' => $identity['fingerprint'],
            'materialisation_pipeline_components' => $identity['components'],
            'publication_evidence' => ['publication_verified' => true],
            'completed_at' => now(),
        ]);
        $chunk = DocumentChunk::factory()->create([
            'workspace_id' => $workspace->id,
            'document_id' => $source->id,
            'ingestion_event_claim_id' => $attempt->id,
            'ordinal' => 0,
        ]);
        WorkspaceCorpusGenerationChunk::query()->create([
            'workspace_id' => $workspace->id,
            'workspace_corpus_generation_id' => $corpus->id,
            'document_chunk_id' => $chunk->id,
        ]);

        $vectors = json_decode((string) file_get_contents(base_path('../../contracts/documents/extraction-artifact/v1/canonicalisation-vectors.json')), true, flags: JSON_THROW_ON_ERROR);
        $bytes = app(StructuredExtractionCanonicaliser::class)->canonicalBytes($vectors['artifact']);
        $artifactKey = "workspaces/{$workspace->public_id}/documents/{$source->public_id}/extraction/source.json";
        Storage::disk('content-clone-acceptance')->put($artifactKey, $bytes);
        $authorisation = DocumentExtractionUploadAuthorisation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'document_id' => $source->id,
            'ingestion_event_claim_id' => $attempt->id,
            'purpose' => 'extraction_artifact_upload',
            'object_key' => $artifactKey,
            'lease_generation' => 1,
            'contract_version' => 'document-extraction-artifact-v1',
            'expires_at' => now()->addHour(),
            'status' => ExtractionUploadStatus::Verified,
            'artifact_sha256' => $vectors['expected']['artifact_sha256'],
            'size_bytes' => strlen($bytes),
            'projection_manifest_digest' => $vectors['expected']['projection_manifest_sha256'],
            'warning_manifest_digest' => $vectors['expected']['warning_manifest_sha256'],
            'element_count' => count($vectors['artifact']['elements']),
            'warning_count' => count($vectors['artifact']['extraction_warnings']),
            'verified_at' => now(),
            'cleanup_state' => ExtractionUploadCleanupState::NotNeeded,
        ]);
        DocumentExtractionArtifact::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'document_id' => $source->id,
            'upload_authorisation_id' => $authorisation->id,
            'object_key' => $artifactKey,
            'contract_version' => 'document-extraction-artifact-v1',
            'artifact_sha256' => $vectors['expected']['artifact_sha256'],
            'size_bytes' => strlen($bytes),
            'projection_manifest_digest' => $vectors['expected']['projection_manifest_sha256'],
            'warning_manifest_digest' => $vectors['expected']['warning_manifest_sha256'],
            'element_count' => count($vectors['artifact']['elements']),
            'warning_count' => count($vectors['artifact']['extraction_warnings']),
            'verified_at' => now(),
            'published_at' => now(),
        ]);

        return [$owner, $source, $attempt, $corpus];
    }
}

final class CloneVectorGatewayFixture implements ContentCloneVectorGateway
{
    public int $cloneCalls = 0;

    public bool $cleanupCalled = false;

    public function __construct(private readonly bool $mismatch = false) {}

    public function clone(
        DocumentContentCloneOperation $operation,
        DocumentContentCloneManifest $manifest,
        string $leaseToken,
    ): array {
        $this->cloneCalls++;

        return [
            'complete' => true,
            'point_count' => $operation->expected_point_count,
            'point_manifest_digest' => $this->mismatch
                ? str_repeat('f', 64)
                : $operation->expected_point_manifest_digest,
        ];
    }

    public function cleanup(DocumentContentCloneOperation $operation): bool
    {
        $this->cleanupCalled = true;

        return true;
    }
}
