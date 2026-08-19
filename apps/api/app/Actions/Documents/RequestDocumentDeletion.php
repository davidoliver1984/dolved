<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Exceptions\DocumentAdministrationException;
use App\Jobs\AdvanceDocumentDeletion;
use App\Models\Document;
use App\Models\DocumentDeletionOperation;
use App\Models\IngestionAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestDocumentDeletion
{
    public function handle(Document $document, User $actor, string $correlationId): DocumentDeletionOperation
    {
        $operation = DB::transaction(function () use ($document, $actor, $correlationId): DocumentDeletionOperation {
            $locked = Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $existing = DocumentDeletionOperation::query()->where('document_id', $locked->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            if (in_array($locked->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true)) {
                throw DocumentAdministrationException::conflict(
                    'document_deletion_ineligible',
                    'The document is already being deleted or has been deleted.',
                );
            }
            $activeAttemptIds = $locked->ingestionAttempts()
                ->whereIn('status', [
                    IngestionAttemptStatus::Open->value,
                    IngestionAttemptStatus::Sealed->value,
                    IngestionAttemptStatus::PublicationAuthorised->value,
                ])
                ->orderBy('id')
                ->pluck('id')
                ->all();
            $operation = DocumentDeletionOperation::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->id,
                'requested_by_user_id' => $actor->id,
                'correlation_id' => $correlationId,
                'status' => DocumentDeletionStatus::AwaitingQuiescence,
                'active_attempt_ids' => $activeAttemptIds,
            ]);
            $locked->forceFill(['status' => DocumentStatus::Deleting])->save();
            IngestionAuditEvent::query()->create([
                'event_id' => $operation->public_id,
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->id,
                'action' => 'deletion_requested',
                'outcome' => 'awaiting_quiescence',
                'context' => [
                    'actor_user_id' => $actor->id,
                    'correlation_id' => $correlationId,
                    'active_attempt_count' => count($activeAttemptIds),
                ],
                'occurred_at' => now(),
            ]);

            return $operation;
        });

        AdvanceDocumentDeletion::dispatch($operation->id);

        return $operation->refresh();
    }
}
