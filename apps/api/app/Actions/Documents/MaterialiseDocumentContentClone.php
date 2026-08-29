<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Ingestion\BuildAndPublishExtractionProjection;
use App\Actions\Ingestion\RecordIngestionAudit;
use App\Contracts\Ingestion\ContentCloneVectorGateway;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\ContentCloneManifestStatus;
use App\Enums\DocumentContentCloneStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use App\Enums\IngestionAttemptOrigin;
use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentContentCloneManifest;
use App\Models\DocumentContentCloneOperation;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\IngestionEventClaim;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Services\Documents\DocumentObjectStorage;
use App\Services\Documents\ExtractionArtifactObjectStorage;
use App\Services\Ingestion\DeterministicVectorPointIdentity;
use App\Services\Ingestion\IngestionCanonicaliser;
use App\Support\Usage\RecordWorkspaceUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

final readonly class MaterialiseDocumentContentClone
{
    public function __construct(
        private DocumentObjectStorage $documents,
        private ExtractionArtifactObjectStorage $artifacts,
        private BuildAndPublishExtractionProjection $projection,
        private DeterministicVectorPointIdentity $pointIdentity,
        private IngestionCanonicaliser $canonicaliser,
        private ContentCloneVectorGateway $vectors,
        private RecordIngestionAudit $audit,
        private RecordWorkspaceUsage $usage,
    ) {}

    public function handle(DocumentContentCloneOperation $operation, string $leaseToken): Document
    {
        $operation->loadMissing([
            'workspace', 'sourceDocument', 'targetDocument',
            'sourceAttempt.chunks', 'sourceAttempt.embeddingSpaceGeneration.embeddingProfile',
            'sourceAttempt.workspaceCorpusGeneration.sparseSpaceGeneration.sparseEmbeddingProfile',
            'targetAttempt',
        ]);
        $targetAttempt = $operation->targetAttempt;
        $this->assertLease($operation, $targetAttempt, $leaseToken);

        try {
            $operation->forceFill([
                'status' => DocumentContentCloneStatus::Copying,
                'copying_at' => now(),
            ])->save();

            $sourceIdentity = $this->documents->copy($operation->sourceDocument, $operation->targetDocument);
            if (! hash_equals($operation->source_checksum_sha256, $sourceIdentity['sha256'])) {
                throw IngestionAttemptException::invalid('clone_source_checksum_mismatch', 'The copied source object does not match its verified predecessor.', 422);
            }
            $operation->targetDocument->forceFill([
                'source_checksum_sha256' => $sourceIdentity['sha256'],
                'checksum_verification_status' => ChecksumVerificationStatus::Verified,
                'checksum_unavailable_reason' => null,
                'size_bytes' => $sourceIdentity['size_bytes'],
                'status' => DocumentStatus::Processing,
            ])->save();

            $sourceArtifact = DocumentExtractionArtifact::query()
                ->where('document_id', $operation->source_document_id)
                ->whereNotNull('published_at')
                ->orderByDesc('id')->firstOrFail();
            $targetArtifact = $this->copyArtifact($operation, $sourceArtifact);
            $projection = $this->projection->handle($targetArtifact);
            [$entries, $chunkDigest] = $this->copyChunks($operation);
            $manifest = $this->writeManifest($operation, $entries);
            $pointIds = array_column($entries, 'target_point_id');
            $pointDigest = $this->canonicaliser->pointManifestDigest($pointIds);

            $operation->forceFill([
                'status' => DocumentContentCloneStatus::Verifying,
                'verifying_at' => now(),
                'expected_point_count' => count($entries),
                'expected_point_manifest_digest' => $pointDigest,
                'layer_evidence' => [
                    'source' => $sourceIdentity,
                    'artifact' => ['sha256' => $targetArtifact->artifact_sha256, 'schema' => $targetArtifact->contract_version],
                    'projection' => ['public_id' => $projection->public_id, 'elements' => $projection->expected_element_count],
                    'warnings' => ['count' => $projection->expected_warning_count],
                    'chunks' => ['count' => count($entries), 'manifest_digest' => $chunkDigest],
                    'manifest' => ['public_id' => $manifest->public_id, 'checksum' => $manifest->checksum_sha256],
                ],
            ])->save();

            $report = $this->vectors->clone($operation->refresh(), $manifest, $leaseToken);
            if (
                ! $report['complete']
                || $report['point_count'] !== count($entries)
                || ! hash_equals($pointDigest, $report['point_manifest_digest'])
            ) {
                throw IngestionAttemptException::invalid('clone_vector_evidence_mismatch', 'The vector clone completeness evidence does not match Laravel authority.', 422);
            }

            return $this->complete($operation, $manifest, $report, $chunkDigest, $leaseToken);
        } catch (Throwable $error) {
            $this->markCleanupRequired($operation, $error);
            throw $error;
        }
    }

    public function cleanupForFallback(DocumentContentCloneOperation $operation): Document
    {
        $operation->refresh()->loadMissing(['targetDocument', 'targetAttempt', 'manifests']);
        if ($operation->status !== DocumentContentCloneStatus::CleanupRequired) {
            throw IngestionAttemptException::invalid('clone_cleanup_not_required', 'The clone is not eligible for cleanup.', 409);
        }
        if (! $this->vectors->cleanup($operation)) {
            throw IngestionAttemptException::invalid('clone_vector_cleanup_unverified', 'Vector cleanup could not be verified absent.', 503);
        }

        foreach ($operation->manifests as $manifest) {
            $this->artifacts->deleteManifestExact($manifest->object_key);
            $manifest->forceFill([
                'cleanup_state' => ExtractionUploadCleanupState::Deleted,
                'cleanup_last_attempted_at' => now(),
            ])->save();
        }

        $artifacts = DocumentExtractionArtifact::query()->where('document_id', $operation->target_document_id)->get();
        foreach ($artifacts as $artifact) {
            $this->artifacts->deleteExact($artifact->object_key);
        }

        return DB::transaction(function () use ($operation, $artifacts): Document {
            $target = Document::query()->whereKey($operation->target_document_id)->lockForUpdate()->firstOrFail();
            $attempt = IngestionEventClaim::query()->whereKey($operation->target_ingestion_event_claim_id)->lockForUpdate()->firstOrFail();
            $locked = DocumentContentCloneOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            $target->forceFill(['active_extraction_projection_generation_id' => null])->save();
            WorkspaceCorpusGenerationChunk::query()
                ->whereIn('document_chunk_id', DocumentChunk::query()->where('document_id', $target->id)->select('id'))
                ->delete();
            DocumentChunk::query()->where('document_id', $target->id)->delete();
            foreach ($artifacts as $artifact) {
                $artifact->delete();
                DocumentExtractionUploadAuthorisation::query()->whereKey($artifact->upload_authorisation_id)->delete();
            }
            if (
                DocumentChunk::query()->where('document_id', $target->id)->exists()
                || DocumentExtractionArtifact::query()->where('document_id', $target->id)->exists()
                || WorkspaceCorpusGenerationChunk::query()->whereIn(
                    'document_chunk_id', DocumentChunk::query()->where('document_id', $target->id)->select('id'),
                )->exists()
            ) {
                throw IngestionAttemptException::invalid('clone_cleanup_incomplete', 'Derived clone content remains present.', 503);
            }
            $attempt->forceFill([
                'status' => IngestionAttemptStatus::Failed,
                'failed_at' => now(),
                'failure_code' => 'content_clone_fallback',
                'failure_message' => 'Clone-derived content was removed before ordinary ingestion fallback.',
                'lease_expires_at' => null,
            ])->save();
            $target->forceFill(['status' => DocumentStatus::Uploaded])->save();
            $locked->forceFill([
                'status' => DocumentContentCloneStatus::FallbackReady,
                'fallback_ready_at' => now(),
            ])->save();

            return $target->refresh();
        });
    }

    private function copyArtifact(
        DocumentContentCloneOperation $operation,
        DocumentExtractionArtifact $source,
    ): DocumentExtractionArtifact {
        $key = sprintf(
            'workspaces/%s/documents/%s/extraction/content-clone/%s.json',
            $operation->workspace->public_id,
            $operation->targetDocument->public_id,
            Str::uuid(),
        );
        $this->artifacts->copyExact($source->object_key, $key);
        $identity = $this->artifacts->inspect($key);
        if (
            $identity === null
            || ! hash_equals($source->artifact_sha256, $identity['sha256'])
            || $identity['contract_version'] !== $source->contract_version
        ) {
            throw IngestionAttemptException::invalid('clone_artifact_identity_mismatch', 'The cloned extraction artifact identity does not match.', 422);
        }
        $authorisation = DocumentExtractionUploadAuthorisation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $operation->workspace_id,
            'document_id' => $operation->target_document_id,
            'ingestion_event_claim_id' => $operation->target_ingestion_event_claim_id,
            'purpose' => 'extraction_artifact_upload',
            'object_key' => $key,
            'lease_generation' => $operation->targetAttempt->lease_generation,
            'contract_version' => $source->contract_version,
            'expires_at' => now()->addHour(),
            'status' => ExtractionUploadStatus::Verified,
            'artifact_sha256' => $source->artifact_sha256,
            'size_bytes' => $source->size_bytes,
            'projection_manifest_digest' => $source->projection_manifest_digest,
            'warning_manifest_digest' => $source->warning_manifest_digest,
            'element_count' => $source->element_count,
            'warning_count' => $source->warning_count,
            'verified_at' => now(),
            'cleanup_state' => ExtractionUploadCleanupState::NotNeeded,
        ]);

        return DocumentExtractionArtifact::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $operation->workspace_id,
            'document_id' => $operation->target_document_id,
            'upload_authorisation_id' => $authorisation->id,
            'object_key' => $key,
            'contract_version' => $source->contract_version,
            'artifact_sha256' => $source->artifact_sha256,
            'size_bytes' => $source->size_bytes,
            'projection_manifest_digest' => $source->projection_manifest_digest,
            'warning_manifest_digest' => $source->warning_manifest_digest,
            'element_count' => $source->element_count,
            'warning_count' => $source->warning_count,
            'verified_at' => now(),
        ]);
    }

    /** @return array{list<array<string, mixed>>, string} */
    private function copyChunks(DocumentContentCloneOperation $operation): array
    {
        $entries = [];
        $manifest = [];
        foreach ($operation->sourceAttempt->chunks->sortBy('ordinal') as $source) {
            $chunkPublicId = Uuid::uuid5(
                Uuid::NAMESPACE_URL,
                'dolved:content-clone:'.$operation->targetAttempt->event_id.':'.$source->public_id,
            )->toString();
            $payload = [
                'chunk_id' => $chunkPublicId,
                'ordinal' => $source->ordinal,
                'text' => $source->text,
                'token_count' => $source->token_count,
                'strategy_name' => $source->strategy_name,
                'strategy_version' => $source->strategy_version,
                'configuration' => $source->configuration,
                'configuration_fingerprint' => $source->configuration_fingerprint,
                'provenance' => [...$source->provenance, [
                    'kind' => 'content_clone',
                    'source_chunk_id' => $source->public_id,
                    'source_event_id' => $operation->sourceAttempt->event_id,
                ]],
            ];
            $contentDigest = $this->canonicaliser->chunkContentDigest($payload);
            $target = DocumentChunk::query()->create([
                'public_id' => $chunkPublicId,
                'workspace_id' => $operation->workspace_id,
                'document_id' => $operation->target_document_id,
                'ingestion_event_claim_id' => $operation->target_ingestion_event_claim_id,
                ...collect($payload)->except('chunk_id')->all(),
                'content_digest' => $contentDigest,
            ]);
            WorkspaceCorpusGenerationChunk::query()->create([
                'workspace_id' => $operation->workspace_id,
                'workspace_corpus_generation_id' => $operation->targetAttempt->workspace_corpus_generation_id,
                'document_chunk_id' => $target->id,
            ]);
            $sourcePoint = $this->pointIdentity->forChunk(
                $operation->sourceAttempt->embeddingSpaceGeneration->public_id,
                $operation->workspace->public_id,
                $operation->sourceAttempt->workspaceCorpusGeneration->public_id,
                $source->public_id,
            );
            $targetPoint = $this->pointIdentity->forChunk(
                $operation->targetAttempt->embeddingSpaceGeneration->public_id,
                $operation->workspace->public_id,
                $operation->targetAttempt->workspaceCorpusGeneration->public_id,
                $chunkPublicId,
            );
            $entries[] = [
                'source_point_id' => $sourcePoint,
                'target_point_id' => $targetPoint,
                'source_chunk_id' => $source->public_id,
                'target_chunk_id' => $chunkPublicId,
                'target_payload' => [
                    'workspace_id' => $operation->workspace->public_id,
                    'document_id' => $operation->targetDocument->public_id,
                    'chunk_id' => $chunkPublicId,
                    'workspace_corpus_generation_id' => $operation->targetAttempt->workspaceCorpusGeneration->public_id,
                    'embedding_space_generation_id' => $operation->targetAttempt->embeddingSpaceGeneration->public_id,
                    'sparse_space_generation_id' => $operation->targetAttempt->workspaceCorpusGeneration->sparseSpaceGeneration?->public_id,
                    'event_id' => $operation->targetAttempt->event_id,
                    'publication_status' => 'provisional',
                ],
            ];
            $manifest[] = ['chunk_id' => $chunkPublicId, 'ordinal' => $target->ordinal, 'content_digest' => $contentDigest];
        }

        return [$entries, $this->canonicaliser->chunkManifestDigest($manifest)];
    }

    /** @param list<array<string, mixed>> $entries */
    private function writeManifest(
        DocumentContentCloneOperation $operation,
        array $entries,
    ): DocumentContentCloneManifest {
        if (count($entries) > (int) config('ingestion.orchestration.content_clone_manifest_max_entries')) {
            throw IngestionAttemptException::invalid('clone_manifest_entry_limit', 'The clone mapping exceeds its configured entry limit.', 422);
        }
        $body = json_encode([
            'schema_version' => config('ingestion.orchestration.content_clone_manifest_schema'),
            'operation_id' => $operation->public_id,
            'event_id' => $operation->targetAttempt->event_id,
            'lease_generation' => $operation->targetAttempt->lease_generation,
            'entries' => $entries,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($body) > (int) config('ingestion.orchestration.content_clone_manifest_max_bytes')) {
            throw IngestionAttemptException::invalid('clone_manifest_size_limit', 'The clone mapping exceeds its configured byte limit.', 422);
        }
        $key = sprintf(
            'workspaces/%s/content-clone-manifests/%s/%d/%s.json',
            $operation->workspace->public_id,
            $operation->targetAttempt->event_id,
            $operation->targetAttempt->lease_generation,
            Str::uuid(),
        );
        $this->artifacts->writeExact($key, $body);

        return DocumentContentCloneManifest::query()->create([
            'public_id' => (string) Str::uuid(),
            'document_content_clone_operation_id' => $operation->id,
            'ingestion_event_claim_id' => $operation->target_ingestion_event_claim_id,
            'lease_generation' => $operation->targetAttempt->lease_generation,
            'object_key' => $key,
            'schema_version' => config('ingestion.orchestration.content_clone_manifest_schema'),
            'entry_count' => count($entries),
            'size_bytes' => strlen($body),
            'checksum_sha256' => hash('sha256', $body),
            'status' => ContentCloneManifestStatus::Verified,
            'expires_at' => now()->addSeconds((int) config('ingestion.orchestration.content_clone_manifest_expiry_seconds')),
            'verified_at' => now(),
            'cleanup_state' => ExtractionUploadCleanupState::NotNeeded,
        ]);
    }

    /** @param array{complete: bool, point_count: int, point_manifest_digest: string} $report */
    private function complete(
        DocumentContentCloneOperation $operation,
        DocumentContentCloneManifest $manifest,
        array $report,
        string $chunkDigest,
        string $leaseToken,
    ): Document {
        return DB::transaction(function () use ($operation, $manifest, $report, $chunkDigest, $leaseToken): Document {
            $target = Document::query()->whereKey($operation->target_document_id)->lockForUpdate()->firstOrFail();
            $attempt = IngestionEventClaim::query()->whereKey($operation->target_ingestion_event_claim_id)->lockForUpdate()->firstOrFail();
            $locked = DocumentContentCloneOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            $this->assertLease($locked, $attempt, $leaseToken);
            if ($locked->status !== DocumentContentCloneStatus::Verifying || $target->status !== DocumentStatus::Processing) {
                throw IngestionAttemptException::invalid('clone_completion_conflict', 'The clone is no longer eligible for completion.', 409);
            }
            $chunkCount = DocumentChunk::query()->where('ingestion_event_claim_id', $attempt->id)->count();
            $assignmentCount = WorkspaceCorpusGenerationChunk::query()
                ->where('workspace_corpus_generation_id', $attempt->workspace_corpus_generation_id)
                ->whereIn('document_chunk_id', DocumentChunk::query()->where('ingestion_event_claim_id', $attempt->id)->select('id'))
                ->count();
            if ($chunkCount !== $report['point_count'] || $assignmentCount !== $chunkCount) {
                throw IngestionAttemptException::invalid('clone_relational_completeness_mismatch', 'Clone relational completeness failed closed.', 422);
            }
            $evidence = [
                'attempt_origin' => IngestionAttemptOrigin::ContentClone->value,
                'expected_chunk_count' => $chunkCount,
                'chunk_manifest_digest' => $chunkDigest,
                'expected_point_count' => $report['point_count'],
                'point_manifest_digest' => $report['point_manifest_digest'],
                'publication_verified' => true,
                'provider_calls' => 0,
            ];
            $attempt->forceFill([
                'status' => IngestionAttemptStatus::Completed,
                'expected_chunk_count' => $chunkCount,
                'chunk_manifest_digest' => $chunkDigest,
                'sealed_at' => now(),
                'expected_point_count' => $report['point_count'],
                'point_manifest_digest' => $report['point_manifest_digest'],
                'embedding_profile_fingerprint' => $attempt->embeddingSpaceGeneration->embeddingProfile->fingerprint,
                'sparse_profile_fingerprint' => $attempt->workspaceCorpusGeneration->sparseSpaceGeneration?->sparseEmbeddingProfile?->fingerprint,
                'publication_evidence' => $evidence,
                'publication_authorised_at' => now(),
                'completed_at' => now(),
                'lease_expires_at' => null,
            ])->save();
            $target->forceFill(['status' => DocumentStatus::Indexed])->save();
            $locked->forceFill([
                'status' => DocumentContentCloneStatus::Indexed,
                'verified_point_count' => $report['point_count'],
                'verified_point_manifest_digest' => $report['point_manifest_digest'],
                'indexed_at' => now(),
            ])->save();
            $manifest->forceFill([
                'status' => ContentCloneManifestStatus::Consumed,
                'consumed_at' => now(),
                'cleanup_state' => ExtractionUploadCleanupState::Eligible,
            ])->save();
            $this->usage->usage($attempt->workspace_id, 'content_clone_attempt', $attempt->event_id, [[
                'provider' => 'qdrant', 'model' => 'vector-copy', 'stage' => 'content_clone',
                'execution' => 'infrastructure', 'request_count' => 1, 'retry_count' => 0,
                'input_tokens' => null, 'cached_input_tokens' => null, 'output_tokens' => null,
                'latency_ms' => null, 'cost_usd' => 0.0, 'cost_basis' => 'zero_cost_local',
                'pricing_snapshot' => null,
            ]]);
            $this->audit->handle($attempt, 'content_clone_completed', 'indexed', [
                'source_event_id' => $operation->sourceAttempt->event_id,
                'attempt_origin' => IngestionAttemptOrigin::ContentClone->value,
            ]);

            return $target->refresh();
        });
    }

    private function assertLease(
        DocumentContentCloneOperation $operation,
        IngestionEventClaim $attempt,
        string $leaseToken,
    ): void {
        if (
            $attempt->attempt_origin !== IngestionAttemptOrigin::ContentClone
            || $attempt->status !== IngestionAttemptStatus::Open
            || $attempt->lease_token_hash === null
            || ! hash_equals($attempt->lease_token_hash, hash('sha256', $leaseToken))
            || $attempt->lease_expires_at === null
            || $attempt->lease_expires_at->isPast()
            || $operation->target_ingestion_event_claim_id !== $attempt->id
        ) {
            throw IngestionAttemptException::invalid('clone_lease_invalid', 'The content-clone lease is stale or invalid.', 409);
        }
    }

    private function markCleanupRequired(DocumentContentCloneOperation $operation, Throwable $error): void
    {
        DocumentContentCloneOperation::query()->whereKey($operation->id)->update([
            'status' => DocumentContentCloneStatus::CleanupRequired->value,
            'failure_code' => $error instanceof IngestionAttemptException ? $error->errorCode : 'content_clone_failed',
            'failure_message' => 'Content cloning failed and requires verified cleanup.',
            'cleanup_required_at' => now(),
            'updated_at' => now(),
        ]);
        DocumentContentCloneManifest::query()
            ->where('document_content_clone_operation_id', $operation->id)
            ->where('cleanup_state', ExtractionUploadCleanupState::NotNeeded->value)
            ->update([
                'cleanup_state' => ExtractionUploadCleanupState::Eligible->value,
                'status' => ContentCloneManifestStatus::Cancelled->value,
                'updated_at' => now(),
            ]);
    }
}
