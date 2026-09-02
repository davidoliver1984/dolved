<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentGovernanceEventKey;
use App\Models\DocumentDeletionOperation;
use App\Models\DocumentGovernanceEvent;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Illuminate\Support\Facades\DB;

final readonly class DetectStuckOrFailedDocumentDeletions
{
    public function __construct(private RecordDocumentGovernanceEvent $events) {}

    public function handle(int $limit = 200): int
    {
        $cutoff = now()->subSeconds((int) config('documents.deletion_stuck_after_seconds', 300));
        $ids = DocumentDeletionOperation::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where('status', DocumentDeletionStatus::Failed->value)
                    ->orWhere(function ($stuck) use ($cutoff): void {
                        $stuck->whereNotIn('status', [
                            DocumentDeletionStatus::Completed->value,
                            DocumentDeletionStatus::Failed->value,
                        ])->where('updated_at', '<=', $cutoff);
                    });
            })
            ->orderBy('id')->limit($limit)->pluck('id');

        $recorded = 0;
        foreach ($ids as $id) {
            $created = DB::transaction(function () use ($id, $cutoff): bool {
                $operation = DocumentDeletionOperation::query()
                    ->with(['workspace', 'document', 'requestedBy'])
                    ->whereKey($id)->lockForUpdate()->first();
                if ($operation === null || $operation->status === DocumentDeletionStatus::Completed) {
                    return false;
                }
                $condition = $operation->status === DocumentDeletionStatus::Failed
                    ? 'failed_permanent'
                    : ($operation->updated_at->lte($cutoff) ? 'stuck' : null);
                if ($condition === null) {
                    return false;
                }
                $occurrence = "{$operation->public_id}:{$operation->lease_generation}:{$condition}";
                $before = DocumentGovernanceEvent::query()
                    ->where('workspace_id', $operation->workspace_id)
                    ->where('occurrence_key', $occurrence)->exists();
                $this->events->record(
                    $operation->workspace,
                    DocumentGovernanceEventKey::DeletionOperationStuckOrFailed,
                    $operation->public_id,
                    $occurrence,
                    [
                        'condition_kind' => $condition,
                        'initiating_user_public_id' => $operation->requestedBy?->public_id,
                        'target_kind' => 'document',
                        'target_public_id' => $operation->document->public_id,
                        'target_display_label' => mb_substr($operation->document->source_filename, 0, 255),
                    ],
                );

                return ! $before;
            }, 3);
            $recorded += $created ? 1 : 0;
        }

        return $recorded;
    }
}
