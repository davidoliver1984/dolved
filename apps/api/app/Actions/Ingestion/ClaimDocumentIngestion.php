<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\IngestionAttemptStatus;
use App\Enums\IngestionClaimOutcome;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\IngestionAttemptException;
use App\Exceptions\IngestionClaimException;
use App\Models\Document;
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
                if ($attempt->lease_expires_at !== null && $attempt->lease_expires_at->isFuture()) {
                    return new IngestionClaimResult(IngestionClaimOutcome::OwnedByAnotherWorker, $document->status);
                }

                if ($attempt->embedding_space_generation_id === null || $attempt->workspace_corpus_generation_id === null) {
                    [$embeddingSpace, $corpus] = $this->resolveGenerations($document);
                    $attempt->embedding_space_generation_id = $embeddingSpace->id;
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
                'workspace_corpus_generation_id' => $corpus->id,
                'status' => IngestionAttemptStatus::Open,
                'lease_token_hash' => hash('sha256', $token),
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
        $corpus = $attempt->workspaceCorpusGeneration()->firstOrFail();

        return new IngestionClaimResult(
            outcome: $outcome,
            documentStatus: DocumentStatus::Processing,
            leaseToken: $token,
            leaseExpiresAt: $attempt->lease_expires_at?->toIso8601String(),
            embeddingSpaceGenerationId: $embedding->public_id,
            workspaceCorpusGenerationId: $corpus->public_id,
            collectionName: $embedding->collection_name,
            vectorName: $embedding->vector_name,
            dimensions: $embedding->dimensions,
            distance: $embedding->distance,
            embeddingProfileFingerprint: $embedding->embeddingProfile->fingerprint,
            resumeSealedAttempt: $resume,
            resetOpenAttempt: $reset,
        );
    }

    private function leaseSeconds(): int
    {
        return max(30, (int) config('ingestion.orchestration.lease_seconds'));
    }
}
