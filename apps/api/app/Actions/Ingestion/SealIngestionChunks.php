<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\IngestionEventClaim;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use App\Services\Ingestion\IngestionCanonicaliser;
use Illuminate\Support\Facades\DB;

class SealIngestionChunks
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly IngestionCanonicaliser $canonicaliser,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): IngestionEventClaim
    {
        return DB::transaction(function () use ($eventId, $payload): IngestionEventClaim {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token']);
            if ($attempt->status !== IngestionAttemptStatus::Open) {
                if (
                    $attempt->expected_chunk_count === $payload['expected_chunk_count']
                    && hash_equals((string) $attempt->chunk_manifest_digest, $payload['chunk_manifest_digest'])
                ) {
                    return $attempt;
                }
                throw IngestionAttemptException::invalid('seal_conflict', 'The attempt was already sealed with different evidence.');
            }
            $chunks = $attempt->chunks()->orderBy('ordinal')->get();
            if ($chunks->count() !== $payload['expected_chunk_count']) {
                throw IngestionAttemptException::invalid('chunk_count_mismatch', 'The submitted chunk count is incomplete.');
            }
            foreach ($chunks as $ordinal => $chunk) {
                if ($chunk->ordinal !== $ordinal || $chunk->configuration_fingerprint !== $payload['configuration_fingerprint']) {
                    throw IngestionAttemptException::invalid('chunk_sequence_invalid', 'Chunks must be gapless and share one configuration fingerprint.');
                }
            }
            $manifest = $chunks->map(fn ($chunk): array => [
                'chunk_id' => $chunk->public_id,
                'ordinal' => $chunk->ordinal,
                'content_digest' => $chunk->content_digest,
            ])->all();
            $digest = $this->canonicaliser->chunkManifestDigest($manifest);
            if (! hash_equals($digest, $payload['chunk_manifest_digest'])) {
                throw IngestionAttemptException::invalid('chunk_manifest_mismatch', 'The chunk manifest does not match authoritative persistence.');
            }
            $attempt->status = IngestionAttemptStatus::Sealed;
            $attempt->expected_chunk_count = $chunks->count();
            $attempt->chunk_manifest_digest = $digest;
            $attempt->sealed_at = now();
            $attempt->save();

            return $attempt;
        });
    }
}
