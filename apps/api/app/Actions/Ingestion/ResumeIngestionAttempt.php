<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\IngestionEventClaim;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use Illuminate\Support\Facades\DB;

class ResumeIngestionAttempt
{
    public function __construct(private readonly IngestionAttemptAuthorizer $authorizer) {}

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function handle(string $eventId, array $payload): array
    {
        return DB::transaction(function () use ($eventId, $payload): array {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->sharedLock()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token']);
            if ($attempt->status === IngestionAttemptStatus::Open || $attempt->sealed_at === null) {
                throw IngestionAttemptException::invalid('attempt_not_sealed', 'Only a sealed attempt can be resumed.');
            }
            if ($attempt->lease_generation < 2) {
                throw IngestionAttemptException::invalid('attempt_not_reclaimed', 'Resume is restricted to a reclaimed attempt.', 403);
            }
            $cursor = (int) ($payload['cursor'] ?? 0);
            $limit = min((int) ($payload['limit'] ?? config('ingestion.orchestration.resume_page_size')), (int) config('ingestion.orchestration.resume_page_size'));
            $chunks = $attempt->chunks()->where('ordinal', '>=', $cursor)->orderBy('ordinal')->limit($limit + 1)->get();
            $hasMore = $chunks->count() > $limit;
            $page = $chunks->take($limit)->values();

            return [
                'status' => $attempt->status->value,
                'expected_chunk_count' => $attempt->expected_chunk_count,
                'chunk_manifest_digest' => $attempt->chunk_manifest_digest,
                'publication_evidence' => $attempt->publication_evidence,
                'chunks' => $page->map(fn ($chunk): array => [
                    'chunk_id' => $chunk->public_id,
                    'ordinal' => $chunk->ordinal,
                    'text' => $chunk->text,
                    'token_count' => $chunk->token_count,
                    'strategy_name' => $chunk->strategy_name,
                    'strategy_version' => $chunk->strategy_version,
                    'configuration' => $chunk->configuration,
                    'configuration_fingerprint' => $chunk->configuration_fingerprint,
                    'provenance' => $chunk->provenance,
                    'content_digest' => $chunk->content_digest,
                ])->all(),
                'next_cursor' => $hasMore ? $cursor + $limit : null,
            ];
        });
    }
}
