<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use App\Enums\IngestionAttemptStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\IngestionEventClaim;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use App\Support\Usage\RecordWorkspaceUsage;
use Illuminate\Support\Facades\DB;

class FailIngestionAttempt
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly RecordIngestionAudit $audit,
        private readonly RecordWorkspaceUsage $usage,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): IngestionEventClaim
    {
        return DB::transaction(function () use ($eventId, $payload): IngestionEventClaim {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token'], allowFailed: true);
            if (($payload['classification'] ?? null) !== 'permanent') {
                throw IngestionAttemptException::invalid('failure_not_permanent', 'Only permanent processing failures may be reported.', 422);
            }
            if ($attempt->status === IngestionAttemptStatus::Failed) {
                if ($attempt->failure_code === $payload['failure_code']) {
                    return $attempt;
                }
                throw IngestionAttemptException::invalid('failure_conflict', 'The attempt was failed with a different outcome.');
            }
            if (in_array($attempt->status, [IngestionAttemptStatus::Completed, IngestionAttemptStatus::PublicationAuthorised], true)) {
                throw IngestionAttemptException::invalid('attempt_ineligible', 'The attempt cannot be failed in its current state.');
            }
            $document = $attempt->document()->lockForUpdate()->firstOrFail();
            if ($document->status !== DocumentStatus::Processing) {
                throw IngestionAttemptException::invalid('document_ineligible', 'The Document is not eligible for failure reporting.');
            }
            $document->forceFill([
                'status' => DocumentStatus::Failed,
                'failure_category' => $payload['failure_code'],
                'failure_message' => $payload['failure_message'],
            ])->save();
            $attempt->forceFill([
                'status' => IngestionAttemptStatus::Failed,
                'failed_at' => now(),
                'failure_code' => $payload['failure_code'],
                'failure_message' => $payload['failure_message'],
            ])->save();
            DocumentExtractionUploadAuthorisation::query()
                ->where('ingestion_event_claim_id', $attempt->id)
                ->where('status', ExtractionUploadStatus::Authorised->value)
                ->update([
                    'status' => ExtractionUploadStatus::Failed->value,
                    'cleanup_state' => ExtractionUploadCleanupState::Eligible->value,
                    'updated_at' => now(),
                ]);
            $this->usage->usage($attempt->workspace_id, 'ingestion_attempt', $attempt->event_id, $payload['usage'] ?? []);
            $this->audit->handle($attempt, 'processing_failed', 'failed');

            return $attempt;
        });
    }
}
