<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Models\IngestionEventClaim;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use Illuminate\Support\Facades\DB;

class RenewIngestionLease
{
    public function __construct(private readonly IngestionAttemptAuthorizer $authorizer) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): IngestionEventClaim
    {
        return DB::transaction(function () use ($eventId, $payload): IngestionEventClaim {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token']);
            $attempt->lease_expires_at = now()->addSeconds(max(30, (int) config('ingestion.orchestration.lease_seconds')));
            $attempt->save();

            return $attempt;
        });
    }
}
