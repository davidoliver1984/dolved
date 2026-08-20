<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\IngestionEventClaim;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use App\Support\Usage\RecordWorkspaceUsage;
use Illuminate\Support\Facades\DB;

class CancelIngestionAttempt
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly RecordIngestionAudit $audit,
        private readonly RecordWorkspaceUsage $usage,
    ) {}

    public function handle(string $eventId, array $payload): IngestionEventClaim
    {
        return DB::transaction(function () use ($eventId, $payload): IngestionEventClaim {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert(
                $attempt,
                $payload['event_id'],
                $payload['workspace_id'],
                $payload['document_id'],
                $payload['lease_token'],
                allowCancelled: true,
                allowDeleting: true,
            );
            if ($attempt->status === IngestionAttemptStatus::Cancelled) {
                return $attempt;
            }
            if (in_array($attempt->status, [IngestionAttemptStatus::Completed, IngestionAttemptStatus::Failed], true)) {
                throw IngestionAttemptException::invalid('attempt_ineligible', 'The attempt cannot be cancelled in its current state.');
            }
            $document = $attempt->document()->lockForUpdate()->firstOrFail();
            if (! in_array($document->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true)) {
                throw IngestionAttemptException::invalid('document_not_deleting', 'Cancellation requires an active document deletion.');
            }
            $attempt->forceFill([
                'status' => IngestionAttemptStatus::Cancelled,
                'cancelled_at' => now(),
                'lease_expires_at' => now(),
            ])->save();
            $this->usage->usage($attempt->workspace_id, 'ingestion_attempt', $attempt->event_id, $payload['usage'] ?? []);
            $this->audit->handle($attempt, 'processing_cancelled', 'cancelled');

            return $attempt;
        });
    }
}
