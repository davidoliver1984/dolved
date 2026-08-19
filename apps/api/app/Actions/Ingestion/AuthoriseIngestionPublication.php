<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\IngestionAttemptStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\IngestionEventClaim;
use App\Services\Ingestion\DeterministicVectorPointIdentity;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use App\Services\Ingestion\IngestionCanonicaliser;
use Illuminate\Support\Facades\DB;

class AuthoriseIngestionPublication
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly IngestionCanonicaliser $canonicaliser,
        private readonly DeterministicVectorPointIdentity $pointIdentity,
        private readonly RecordIngestionAudit $audit,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): IngestionEventClaim
    {
        return DB::transaction(function () use ($eventId, $payload): IngestionEventClaim {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token']);
            $attempt->loadMissing([
                'document',
                'embeddingSpaceGeneration.embeddingProfile',
                'workspaceCorpusGeneration.sparseSpaceGeneration.sparseEmbeddingProfile',
                'chunks',
            ]);

            if ($attempt->document->status !== DocumentStatus::Processing) {
                throw IngestionAttemptException::invalid('document_ineligible', 'The Document is not eligible for publication.');
            }
            if ($attempt->status === IngestionAttemptStatus::PublicationAuthorised) {
                if ($attempt->publication_evidence === $this->evidence($payload)) {
                    return $attempt;
                }
                throw IngestionAttemptException::invalid('publication_conflict', 'Publication was already authorised for different evidence.');
            }
            if ($attempt->status !== IngestionAttemptStatus::Sealed) {
                throw IngestionAttemptException::invalid('attempt_not_sealed', 'Publication requires a sealed attempt.');
            }
            if ($attempt->embeddingSpaceGeneration->status !== EmbeddingSpaceGenerationStatus::Available) {
                throw IngestionAttemptException::invalid('embedding_space_unavailable', 'The embedding space is not available.');
            }
            if ($attempt->embeddingSpaceGeneration->embeddingProfile->fingerprint !== $payload['embedding_profile_fingerprint']) {
                throw IngestionAttemptException::invalid('embedding_profile_mismatch', 'The embedding profile fingerprint does not match.');
            }
            if (
                $attempt->embeddingSpaceGeneration->public_id !== $payload['embedding_space_generation_id']
                || $attempt->workspaceCorpusGeneration->public_id !== $payload['workspace_corpus_generation_id']
            ) {
                throw IngestionAttemptException::invalid('generation_mismatch', 'Publication generation identity does not match the attempt.');
            }
            $sparse = $attempt->workspaceCorpusGeneration->sparseSpaceGeneration;
            if (
                ($payload['sparse_space_generation_id'] ?? null) !== $sparse?->public_id
                || ($payload['sparse_profile_fingerprint'] ?? null) !== $sparse?->sparseEmbeddingProfile?->fingerprint
            ) {
                throw IngestionAttemptException::invalid('sparse_generation_mismatch', 'Publication sparse generation lineage does not match the attempt.');
            }
            if (
                $attempt->expected_chunk_count !== $payload['expected_chunk_count']
                || ! hash_equals((string) $attempt->chunk_manifest_digest, $payload['chunk_manifest_digest'])
            ) {
                throw IngestionAttemptException::invalid('chunk_manifest_mismatch', 'Publication evidence does not match the sealed chunks.');
            }

            $pointManifest = $attempt->chunks->sortBy('ordinal')->map(fn ($chunk): array => [
                'point_id' => $this->pointIdentity->forChunk(
                    $attempt->embeddingSpaceGeneration->public_id,
                    $attempt->workspace->public_id,
                    $attempt->workspaceCorpusGeneration->public_id,
                    $chunk->public_id,
                ),
            ])->values()->all();
            $pointIds = array_column($pointManifest, 'point_id');
            $pointDigest = $this->canonicaliser->pointManifestDigest($pointIds);
            if (
                count($pointManifest) !== $payload['expected_point_count']
                || collect($pointIds)->sort()->values()->all() !== collect($payload['point_ids'])->sort()->values()->all()
                || ! hash_equals($pointDigest, $payload['point_manifest_digest'])
            ) {
                throw IngestionAttemptException::invalid('point_manifest_mismatch', 'Point evidence does not match deterministic authoritative identities.');
            }

            $attempt->forceFill([
                'status' => IngestionAttemptStatus::PublicationAuthorised,
                'expected_point_count' => count($pointManifest),
                'point_manifest_digest' => $pointDigest,
                'embedding_profile_fingerprint' => $payload['embedding_profile_fingerprint'],
                'sparse_profile_fingerprint' => $payload['sparse_profile_fingerprint'] ?? null,
                'publication_evidence' => $this->evidence($payload),
                'publication_authorised_at' => now(),
            ])->save();
            $generation = $attempt->workspaceCorpusGeneration()->lockForUpdate()->firstOrFail();
            if ($generation->status === WorkspaceCorpusGenerationStatus::Building) {
                $generation->forceFill([
                    'status' => WorkspaceCorpusGenerationStatus::Verifying,
                ])->save();
            }
            $this->audit->handle($attempt, 'publication_authorised', 'accepted');

            return $attempt;
        });
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function evidence(array $payload): array
    {
        return collect($payload)->only([
            'expected_chunk_count', 'chunk_manifest_digest', 'expected_point_count',
            'point_manifest_digest', 'embedding_profile_fingerprint',
            'sparse_profile_fingerprint', 'embedding_space_generation_id',
            'sparse_space_generation_id', 'workspace_corpus_generation_id', 'warnings',
        ])->all();
    }
}
