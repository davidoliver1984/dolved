<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Models\DocumentDeletionOperation;
use App\Models\IngestionAuditEvent;
use App\Models\IngestionEventClaim;
use App\Services\Dify\DifyKnowledgeClient;
use App\Services\Documents\DocumentObjectStorage;
use Illuminate\Support\Facades\DB;

final readonly class CompleteDifyDocumentDeletion
{
    public function __construct(
        private DifyKnowledgeClient $dify,
        private DocumentObjectStorage $storage,
    ) {}

    public function handle(int $operationId): bool
    {
        $operation = DocumentDeletionOperation::query()
            ->with(['document.difyBindings'])
            ->findOrFail($operationId);
        if ($operation->status === DocumentDeletionStatus::Completed) {
            return true;
        }
        if (! $this->isQuiescent($operation)) {
            return false;
        }

        foreach ($operation->document->difyBindings as $binding) {
            if ($binding->remote_document_id !== null && $binding->sync_status !== 'deleted') {
                if ($binding->sync_status !== 'disabled') {
                    $this->dify->changeStatus($binding->dataset_id, $binding->remote_document_id, 'disable');
                    $binding->forceFill(['sync_status' => 'disabled'])->save();
                }
                $this->dify->deleteDocument($binding->dataset_id, $binding->remote_document_id);
            }
            $binding->forceFill(['sync_status' => 'deleted', 'failure_code' => null])->save();
        }

        $this->storage->delete($operation->document);

        DB::transaction(function () use ($operation): void {
            $locked = DocumentDeletionOperation::query()->with('document')->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === DocumentDeletionStatus::Completed) {
                return;
            }
            $locked->document->forceFill([
                'status' => DocumentStatus::Deleted,
                'failure_category' => null,
                'failure_message' => null,
            ])->save();
            $locked->forceFill([
                'status' => DocumentDeletionStatus::Completed,
                'cleanup_evidence' => [
                    'dify_documents_removed' => true,
                    'object_removed' => true,
                ],
                'completed_at' => now(),
            ])->save();
            IngestionAuditEvent::query()->create([
                'event_id' => $locked->public_id,
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->document_id,
                'action' => 'deletion_completed',
                'outcome' => 'deleted',
                'context' => ['processing_driver' => 'dify'],
                'occurred_at' => now(),
            ]);
        });

        return true;
    }

    private function isQuiescent(DocumentDeletionOperation $operation): bool
    {
        if ($operation->active_attempt_ids === []) {
            return true;
        }

        return IngestionEventClaim::query()
            ->whereIn('id', $operation->active_attempt_ids)
            ->get()
            ->every(fn (IngestionEventClaim $attempt): bool => in_array($attempt->status, [
                IngestionAttemptStatus::Cancelled,
                IngestionAttemptStatus::Failed,
                IngestionAttemptStatus::Completed,
            ], true) || ($attempt->lease_expires_at !== null && $attempt->lease_expires_at->isPast()));
    }
}
