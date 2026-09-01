<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkItemStatus;
use App\Models\BulkOperation;
use App\Models\BulkOperationItem;
use App\Models\BulkOperationItemAttempt;
use App\Support\BulkOperations\RecordBulkOperationAudit;
use Illuminate\Support\Facades\DB;

final readonly class ConvergeBulkOperationCancellation
{
    public function __construct(
        private RecordBulkOperationAudit $audit,
        private FinalizeBulkOperationAttempt $finalize,
    ) {}

    public function handle(BulkOperation $operation): BulkOperation
    {
        return DB::transaction(function () use ($operation): BulkOperation {
            $parent = BulkOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($parent->cancellation_requested_at === null || $parent->status->isTerminal()) {
                return $parent;
            }
            $items = BulkOperationItem::query()->where('bulk_operation_id', $parent->id)
                ->whereIn('execution_status', [BulkItemStatus::Eligible->value, BulkItemStatus::FailedRetryable->value])
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                $hasUnincorporated = BulkOperationItemAttempt::query()
                    ->where('bulk_operation_item_id', $item->id)
                    ->where('generation', '>', $item->incorporated_attempt_generation ?? 0)->exists();
                if ($hasUnincorporated) {
                    continue;
                }
                $status = $item->execution_status === BulkItemStatus::Eligible
                    ? BulkItemStatus::Cancelled
                    : BulkItemStatus::FailedPermanent;
                $event = $this->audit->record($parent, 'bulk_operation.item_cancelled', 'laravel.bulk-worker', [
                    'item_status' => $status->value,
                    'operation_type' => $parent->operation_type->value,
                    'terminal_reason' => 'cancellation_requested',
                ], $item);
                $item->forceFill([
                    'execution_status' => $status,
                    'terminal_reason' => 'cancellation_requested',
                    'completed_at' => now(),
                    'audit_event_id' => $event->id,
                ])->save();
            }

            return $this->finalize->convergeLockedParent($parent);
        }, 3);
    }
}
