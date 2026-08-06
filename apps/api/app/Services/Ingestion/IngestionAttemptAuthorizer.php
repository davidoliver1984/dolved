<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\IngestionEventClaim;

class IngestionAttemptAuthorizer
{
    public function assert(
        IngestionEventClaim $attempt,
        string $eventId,
        string $workspaceId,
        string $documentId,
        string $leaseToken,
        bool $allowCompleted = false,
        bool $allowFailed = false,
    ): void {
        if (
            $attempt->event_id !== $eventId
            || $attempt->workspace_public_id !== $workspaceId
            || $attempt->document_public_id !== $documentId
        ) {
            throw IngestionAttemptException::invalid(
                'attempt_scope_mismatch',
                'The ingestion attempt does not match the requested scope.',
            );
        }

        if (
            $attempt->lease_token_hash === null
            || ! hash_equals($attempt->lease_token_hash, hash('sha256', $leaseToken))
        ) {
            throw IngestionAttemptException::invalid(
                'stale_lease',
                'The processing lease is stale or has been superseded.',
            );
        }

        $terminalRetry = ($allowCompleted && $attempt->status === IngestionAttemptStatus::Completed)
            || ($allowFailed && $attempt->status === IngestionAttemptStatus::Failed);
        if (! $terminalRetry && ($attempt->lease_expires_at === null || $attempt->lease_expires_at->isPast())) {
            throw IngestionAttemptException::invalid(
                'expired_lease',
                'The processing lease has expired.',
            );
        }
    }
}
