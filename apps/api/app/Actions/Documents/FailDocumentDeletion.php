<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Exceptions\DocumentAdministrationException;
use App\Models\DocumentDeletionOperation;
use App\Models\IngestionAuditEvent;
use Illuminate\Support\Facades\DB;

class FailDocumentDeletion
{
    public function handle(string $eventId, array $payload): DocumentDeletionOperation
    {
        $operation = DB::transaction(function () use ($eventId, $payload): DocumentDeletionOperation {
            $operation = DocumentDeletionOperation::query()->with('document.workspace')->where('public_id', $eventId)->lockForUpdate()->firstOrFail();
            if ($operation->lease_token_hash === null
                || ! hash_equals($operation->lease_token_hash, hash('sha256', $payload['lease_token']))
                || $operation->document->public_id !== $payload['document_id']
                || $operation->document->workspace->public_id !== $payload['workspace_id']) {
                throw DocumentAdministrationException::conflict('stale_deletion_lease', 'The deletion lease is stale or does not match the authorised scope.');
            }
            $operation->forceFill([
                'status' => $payload['classification'] === 'retryable' ? DocumentDeletionStatus::Queued : DocumentDeletionStatus::Failed,
                'failure_code' => $payload['failure_code'],
                'failure_message' => $payload['failure_message'],
                'lease_token_hash' => null,
                'lease_expires_at' => null,
            ])->save();
            IngestionAuditEvent::query()->firstOrCreate(
                [
                    'event_id' => $operation->public_id,
                    'action' => 'deletion_failed',
                    'outcome' => $payload['classification'],
                ],
                [
                    'workspace_id' => $operation->workspace_id,
                    'document_id' => $operation->document_id,
                    'context' => ['failure_code' => $payload['failure_code']],
                    'occurred_at' => now(),
                ],
            );

            return $operation;
        });

        if ($operation->document_family_deletion_operation_id !== null) {
            app(ReconcileDocumentFamilyDeletion::class)->handle($operation->document_family_deletion_operation_id);
        }

        return $operation;
    }
}
