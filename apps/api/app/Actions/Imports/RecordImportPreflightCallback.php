<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\DocumentGovernanceEventKey;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportPreflightAttemptStatus;
use App\Enums\ImportPreflightRejectionReason;
use App\Enums\ImportPreflightResult;
use App\Enums\ImportPreflightStatus;
use App\Exceptions\ImportPreflightException;
use App\Models\ImportPreflightAttempt;
use App\Services\Imports\ImportPreflightContractValidator;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use App\Support\Imports\ImportPreflightPayloadDigest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RecordImportPreflightCallback
{
    public function __construct(
        private readonly ImportPreflightContractValidator $contracts,
        private readonly ImportPreflightPayloadDigest $digests,
        private readonly RecordDocumentGovernanceEvent $events,
    ) {}

    /** @param array<string, mixed> $payload */
    public function complete(string $eventId, array $payload): string
    {
        $this->contracts->validateComplete($payload);

        return $this->record($eventId, $payload, false);
    }

    /** @param array<string, mixed> $payload */
    public function fail(string $eventId, array $payload): string
    {
        $this->contracts->validateFail($payload);

        return $this->record($eventId, $payload, true);
    }

    /** @param array<string, mixed> $payload */
    private function record(string $eventId, array $payload, bool $failure): string
    {
        $digest = $this->digests->hash($payload);

        return DB::transaction(function () use ($eventId, $payload, $failure, $digest): string {
            $attempt = ImportPreflightAttempt::query()->with(['item.batch.initiatedBy', 'item.workspace'])->where('event_id', $eventId)->lockForUpdate()->first();
            if ($attempt === null) {
                throw ImportPreflightException::conflict('unknown_event');
            }
            $this->assertIdentity($attempt, $payload);
            if ($attempt->status !== ImportPreflightAttemptStatus::Open) {
                if (hash_equals((string) $attempt->reported_payload_sha256, $digest)) {
                    return 'duplicate';
                }
                throw ImportPreflightException::conflict('conflicting_replay');
            }
            if ($attempt->lease_expires_at->isPast()) {
                throw ImportPreflightException::conflict('expired_lease');
            }

            $item = $attempt->item;
            $attempt->forceFill([
                'status' => $failure ? ImportPreflightAttemptStatus::Failed : ImportPreflightAttemptStatus::Completed,
                'result' => $failure ? null : $payload['result'],
                'diagnostic_code' => $payload['diagnostic_code'],
                'reported_payload_sha256' => $digest,
                'completed_at' => now(),
            ])->save();

            $actionable = $item->batch->status !== ImportBatchStatus::Expired
                && $item->preflight_status === ImportPreflightStatus::Pending;
            if ($actionable && ! $failure) {
                $result = ImportPreflightResult::from((string) $payload['result']);
                if ($result === ImportPreflightResult::Readable) {
                    $item->forceFill([
                        'source_checksum_sha256' => $payload['source_checksum_sha256'],
                        'media_type' => $payload['media_type'],
                        'size_bytes' => $payload['size_bytes'],
                        'preflight_status' => ImportPreflightStatus::Verified,
                        'preflight_rejection_reason' => null,
                    ])->save();
                } else {
                    $item->forceFill([
                        'preflight_status' => ImportPreflightStatus::Rejected,
                        'preflight_rejection_reason' => ImportPreflightRejectionReason::from($result->value),
                    ])->save();
                }
            }

            if ($failure) {
                $this->events->record(
                    $item->workspace,
                    DocumentGovernanceEventKey::ImportItemProcessingFailed,
                    $item->public_id,
                    "{$item->public_id}:{$attempt->event_id}:processing_failed",
                    [
                        'initiating_user_public_id' => $item->batch->initiatedBy?->public_id,
                        'target_kind' => 'import_item',
                        'target_public_id' => $item->public_id,
                        'target_display_label' => mb_substr($item->source_filename ?? 'Staged import', 0, 255),
                    ],
                );
            } elseif ($actionable && isset($result) && $result !== ImportPreflightResult::Readable) {
                $this->events->record(
                    $item->workspace,
                    DocumentGovernanceEventKey::ImportItemRequiresUserAction,
                    $item->public_id,
                    "{$item->public_id}:{$result->value}:{$attempt->event_id}",
                    [
                        'action_category' => $result->value,
                        'initiating_user_public_id' => $item->batch->initiatedBy?->public_id,
                        'target_kind' => 'import_item',
                        'target_public_id' => $item->public_id,
                        'target_display_label' => mb_substr($item->source_filename ?? 'Staged import', 0, 255),
                    ],
                );
            }

            Log::info('Import preflight callback recorded.', [
                'event_name' => 'import.preflight.callback_recorded.v1',
                'event_id' => $attempt->event_id,
                'workspace_id' => $item->workspace->public_id,
                'import_item_id' => $item->public_id,
                'lease_generation' => $attempt->lease_generation,
                'callback_outcome' => $failure ? 'failed' : (string) $payload['result'],
                'item_mutated' => $actionable && ! $failure,
            ]);

            return 'recorded';
        });
    }

    /** @param array<string, mixed> $payload */
    private function assertIdentity(ImportPreflightAttempt $attempt, array $payload): void
    {
        if ((string) $payload['event_id'] !== $attempt->event_id
            || (string) $payload['workspace_id'] !== $attempt->item->workspace->public_id
            || (string) $payload['import_item_id'] !== $attempt->item->public_id
            || (string) $payload['staged_object_key'] !== $attempt->staged_object_key
            || (int) $payload['lease_generation'] !== $attempt->lease_generation
            || ! hash_equals($attempt->lease_token_hash, hash('sha256', (string) $payload['lease_token']))) {
            throw ImportPreflightException::conflict('stale_or_mismatched_lease');
        }
    }
}
