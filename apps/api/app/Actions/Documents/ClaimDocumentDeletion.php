<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Exceptions\DocumentAdministrationException;
use App\Models\DocumentDeletionOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClaimDocumentDeletion
{
    /** @return array<string, mixed> */
    public function handle(array $event, string $payloadSha256): array
    {
        return DB::transaction(function () use ($event, $payloadSha256): array {
            $operation = DocumentDeletionOperation::query()
                ->with(['document.workspace'])
                ->where('public_id', $event['event_id'])
                ->lockForUpdate()
                ->firstOrFail();
            if ($operation->document->public_id !== $event['document_id']
                || $operation->document->workspace->public_id !== $event['workspace_id']
                || $operation->correlation_id !== $event['correlation_id']
                || $operation->vector_scopes !== $event['vector_scopes']) {
                throw DocumentAdministrationException::conflict('deletion_scope_mismatch', 'The deletion event does not match its durable operation.');
            }
            if ($operation->cleanup_evidence !== null
                && ($operation->cleanup_evidence['event_payload_sha256'] ?? null) !== $payloadSha256) {
                throw DocumentAdministrationException::conflict('deletion_event_reused', 'The deletion event identity has conflicting content.');
            }
            if ($operation->status === DocumentDeletionStatus::Completed) {
                return ['outcome' => 'already_completed'];
            }
            if ($operation->status === DocumentDeletionStatus::Processing
                && $operation->lease_expires_at?->isFuture()) {
                return ['outcome' => 'owned_by_another_worker'];
            }
            $token = (string) Str::uuid();
            $operation->forceFill([
                'status' => DocumentDeletionStatus::Processing,
                'lease_token_hash' => hash('sha256', $token),
                'lease_generation' => $operation->lease_generation + 1,
                'lease_expires_at' => now()->addSeconds(max(30, (int) config('ingestion.orchestration.lease_seconds'))),
                'cleanup_evidence' => ['event_payload_sha256' => $payloadSha256],
                'failure_code' => null,
                'failure_message' => null,
            ])->save();

            return [
                'outcome' => 'claimed',
                'lease_token' => $token,
                'lease_expires_at' => $operation->lease_expires_at?->toIso8601String(),
                'vector_scopes' => $operation->vector_scopes ?? [],
            ];
        });
    }
}
