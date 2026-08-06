<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\DocumentChunk;
use App\Models\IngestionEventClaim;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use App\Services\Ingestion\IngestionCanonicaliser;
use Illuminate\Support\Facades\DB;

class SubmitIngestionChunks
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly IngestionCanonicaliser $canonicaliser,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): int
    {
        return DB::transaction(function () use ($eventId, $payload): int {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token']);
            if ($attempt->status !== IngestionAttemptStatus::Open) {
                throw IngestionAttemptException::invalid('attempt_sealed', 'A sealed chunk set is immutable.');
            }

            foreach ($payload['chunks'] as $chunk) {
                $calculated = $this->canonicaliser->chunkContentDigest($chunk);
                if (! hash_equals($calculated, $chunk['content_digest'])) {
                    throw IngestionAttemptException::invalid('chunk_digest_mismatch', 'A submitted chunk digest is invalid.', 422);
                }
                $existing = DocumentChunk::query()
                    ->where('ingestion_event_claim_id', $attempt->id)
                    ->where('public_id', $chunk['chunk_id'])
                    ->first();
                if ($existing !== null) {
                    if (! hash_equals((string) $existing->content_digest, $calculated)) {
                        throw IngestionAttemptException::invalid('chunk_conflict', 'A chunk identity was reused with different content.');
                    }

                    continue;
                }
                $ordinalConflict = DocumentChunk::query()
                    ->where('ingestion_event_claim_id', $attempt->id)
                    ->where('ordinal', $chunk['ordinal'])
                    ->exists();
                if ($ordinalConflict) {
                    throw IngestionAttemptException::invalid('chunk_ordinal_conflict', 'A chunk ordinal was reused with a different identity.');
                }
                DocumentChunk::query()->forceCreate([
                    'public_id' => $chunk['chunk_id'],
                    'workspace_id' => $attempt->workspace_id,
                    'document_id' => $attempt->document_id,
                    'ingestion_event_claim_id' => $attempt->id,
                    'ordinal' => $chunk['ordinal'],
                    'text' => $chunk['text'],
                    'token_count' => $chunk['token_count'],
                    'strategy_name' => $chunk['strategy_name'],
                    'strategy_version' => $chunk['strategy_version'],
                    'configuration' => $chunk['configuration'],
                    'configuration_fingerprint' => $chunk['configuration_fingerprint'],
                    'provenance' => $chunk['provenance'],
                    'content_digest' => $calculated,
                ]);
            }

            return $attempt->chunks()->count();
        });
    }
}
