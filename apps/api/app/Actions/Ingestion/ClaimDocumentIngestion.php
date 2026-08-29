<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use App\Enums\IngestionAttemptStatus;
use App\Enums\IngestionClaimOutcome;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\IngestionAttemptException;
use App\Exceptions\IngestionClaimException;
use App\Models\Document;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\IngestionEventClaim;
use App\Models\WorkspaceCorpusGeneration;
use App\Support\Ingestion\IngestionClaimResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClaimDocumentIngestion
{
    /** @param array<string, mixed> $event */
    public function handle(array $event, string $payloadSha256): IngestionClaimResult
    {
        return DB::transaction(function () use ($event, $payloadSha256): IngestionClaimResult {
            $document = Document::query()
                ->where('public_id', $event['document_id'])
                ->whereHas('workspace', fn (Builder $query): Builder => $query->where('public_id', $event['workspace_id']))
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw IngestionClaimException::unknownDocument();
            }

            $attempt = IngestionEventClaim::query()
                ->where('event_id', $event['event_id'])
                ->lockForUpdate()
                ->first();

            if ($attempt !== null) {
                $this->assertIdentity($attempt, $event, $payloadSha256);

                if ($attempt->status === IngestionAttemptStatus::Completed) {
                    return new IngestionClaimResult(IngestionClaimOutcome::AlreadyCompleted, DocumentStatus::Indexed);
                }
                if ($attempt->status === IngestionAttemptStatus::Failed) {
                    return new IngestionClaimResult(IngestionClaimOutcome::PermanentlyFailed, DocumentStatus::Failed);
                }
                if ($attempt->status === IngestionAttemptStatus::Cancelled
                    || in_array($document->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true)) {
                    return new IngestionClaimResult(IngestionClaimOutcome::StaleEvent, $document->status);
                }
                if ($attempt->lease_expires_at !== null && $attempt->lease_expires_at->isFuture()) {
                    return new IngestionClaimResult(IngestionClaimOutcome::OwnedByAnotherWorker, $document->status);
                }

                if ($attempt->embedding_space_generation_id === null || $attempt->workspace_corpus_generation_id === null) {
                    [$embeddingSpace, $corpus] = $this->resolveGenerations($document);
                    $attempt->embedding_space_generation_id = $embeddingSpace->id;
                    $attempt->sparse_space_generation_id = $corpus->sparse_space_generation_id;
                    $attempt->workspace_corpus_generation_id = $corpus->id;
                }

                $wasSealed = $attempt->status !== IngestionAttemptStatus::Open;
                if (! $wasSealed) {
                    $attempt->chunks()->delete();
                    $attempt->expected_chunk_count = null;
                    $attempt->chunk_manifest_digest = null;
                    $attempt->sealed_at = null;
                    $attempt->publication_evidence = null;
                    $attempt->publication_authorised_at = null;
                }
                $token = $this->issueLease($attempt);
                DocumentExtractionUploadAuthorisation::query()
                    ->where('ingestion_event_claim_id', $attempt->id)
                    ->where('lease_generation', $attempt->lease_generation)
                    ->where('status', ExtractionUploadStatus::Authorised->value)
                    ->update([
                        'status' => ExtractionUploadStatus::Cancelled->value,
                        'cleanup_state' => ExtractionUploadCleanupState::Eligible->value,
                        'updated_at' => now(),
                    ]);
                $attempt->lease_generation++;
                $attempt->save();

                return $this->proceedResult(
                    $attempt,
                    IngestionClaimOutcome::Reclaimed,
                    $token,
                    $wasSealed,
                    ! $wasSealed,
                );
            }

            if ($document->status !== DocumentStatus::Queued) {
                if (in_array($document->status, [DocumentStatus::Processing, DocumentStatus::Indexed, DocumentStatus::Failed, DocumentStatus::Deleting, DocumentStatus::Deleted], true)) {
                    return new IngestionClaimResult(IngestionClaimOutcome::StaleEvent, $document->status);
                }

                return new IngestionClaimResult(IngestionClaimOutcome::IneligibleState, $document->status);
            }

            [$embeddingSpace, $corpus] = $this->resolveGenerations($document);
            $token = (string) Str::uuid();
            $attempt = IngestionEventClaim::query()->create([
                'event_id' => $event['event_id'],
                'workspace_id' => $document->workspace_id,
                'document_id' => $document->id,
                'workspace_public_id' => $event['workspace_id'],
                'document_public_id' => $event['document_id'],
                'correlation_id' => $event['correlation_id'],
                'payload_sha256' => $payloadSha256,
                'claimed_at' => now(),
                'embedding_space_generation_id' => $embeddingSpace->id,
                'sparse_space_generation_id' => $corpus->sparse_space_generation_id,
                'workspace_corpus_generation_id' => $corpus->id,
                'status' => IngestionAttemptStatus::Open,
                'lease_token_hash' => hash('sha256', $token),
                'lease_generation' => 1,
                'lease_expires_at' => now()->addSeconds($this->leaseSeconds()),
            ]);
            $document->status = DocumentStatus::Processing;
            $document->save();

            return $this->proceedResult($attempt, IngestionClaimOutcome::Claimed, $token);
        });
    }

    /** @param array<string, mixed> $event */
    private function assertIdentity(IngestionEventClaim $attempt, array $event, string $payloadSha256): void
    {
        if (
            $attempt->workspace_public_id !== $event['workspace_id']
            || $attempt->document_public_id !== $event['document_id']
            || $attempt->correlation_id !== $event['correlation_id']
            || $attempt->payload_sha256 !== $payloadSha256
        ) {
            throw IngestionClaimException::eventIdentityReused();
        }
    }

    /** @return array{EmbeddingSpaceGeneration, WorkspaceCorpusGeneration} */
    private function resolveGenerations(Document $document): array
    {
        $workspace = $document->workspace()->lockForUpdate()->firstOrFail();
        $corpus = $workspace->activeCorpusGeneration;
        $embeddingSpace = $corpus?->embeddingSpaceGeneration;
        if ($embeddingSpace === null) {
            $embeddingSpace = EmbeddingSpaceGeneration::query()
                ->with('embeddingProfile')
                ->where('status', EmbeddingSpaceGenerationStatus::Available->value)
                ->orderByDesc('id')
                ->sharedLock()
                ->first();
        }
        if ($embeddingSpace === null) {
            throw IngestionAttemptException::invalid(
                'embedding_space_unavailable',
                'No available embedding space has been provisioned.',
                503,
            );
        }

        if ($corpus === null) {
            $corpus = WorkspaceCorpusGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->where('embedding_space_generation_id', $embeddingSpace->id)
                ->whereIn('status', [WorkspaceCorpusGenerationStatus::Building->value, WorkspaceCorpusGenerationStatus::Verifying->value])
                ->lockForUpdate()
                ->first();
        }
        if ($corpus === null) {
            $corpus = WorkspaceCorpusGeneration::query()->forceCreate([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'embedding_space_generation_id' => $embeddingSpace->id,
                'status' => WorkspaceCorpusGenerationStatus::Building,
            ]);
        }

        return [$embeddingSpace, $corpus];
    }

    private function issueLease(IngestionEventClaim $attempt): string
    {
        $token = (string) Str::uuid();
        $attempt->lease_token_hash = hash('sha256', $token);
        $attempt->lease_expires_at = now()->addSeconds($this->leaseSeconds());

        return $token;
    }

    private function proceedResult(
        IngestionEventClaim $attempt,
        IngestionClaimOutcome $outcome,
        string $token,
        bool $resume = false,
        bool $reset = false,
    ): IngestionClaimResult {
        $embedding = $attempt->embeddingSpaceGeneration()->with('embeddingProfile')->firstOrFail();
        $corpus = $attempt->workspaceCorpusGeneration()
            ->with('sparseSpaceGeneration.sparseEmbeddingProfile')
            ->firstOrFail();
        $sparse = $corpus->sparseSpaceGeneration;

        return new IngestionClaimResult(
            outcome: $outcome,
            documentStatus: DocumentStatus::Processing,
            leaseToken: $token,
            leaseExpiresAt: $attempt->lease_expires_at?->toIso8601String(),
            leaseGeneration: $attempt->lease_generation,
            embeddingSpaceGenerationId: $embedding->public_id,
            workspaceCorpusGenerationId: $corpus->public_id,
            collectionName: $embedding->collection_name,
            vectorName: $embedding->vector_name,
            dimensions: $embedding->dimensions,
            distance: $embedding->distance,
            embeddingProfileFingerprint: $embedding->embeddingProfile->fingerprint,
            sparseSpaceGenerationId: $sparse?->public_id,
            sparseVectorName: $sparse?->vector_name,
            sparseProfileFingerprint: $sparse?->sparseEmbeddingProfile->fingerprint,
            sparseProfile: $sparse === null ? null : collect(
                $sparse->sparseEmbeddingProfile->getAttributes()
            )->only([
                'provider', 'model', 'tokenizer', 'tokenizer_revision',
                'output_representation', 'max_input_tokens', 'document_input_type',
                'query_input_type', 'model_revision', 'adapter_version',
            ])->all(),
            resumeSealedAttempt: $resume,
            resetOpenAttempt: $reset,
        );
    }

    private function leaseSeconds(): int
    {
        return max(30, (int) config('ingestion.orchestration.lease_seconds'));
    }
}
