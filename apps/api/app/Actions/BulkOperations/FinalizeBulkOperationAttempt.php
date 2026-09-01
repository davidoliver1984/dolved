<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkAttemptStatus;
use App\Enums\BulkItemStatus;
use App\Models\BulkOperation;
use App\Models\BulkOperationItem;
use App\Models\BulkOperationItemAttempt;
use App\Support\BulkOperations\RecordBulkOperationAudit;
use Illuminate\Support\Facades\DB;

final readonly class FinalizeBulkOperationAttempt
{
    public function __construct(
        private RecordBulkOperationAudit $audit,
        private ResolveBulkOperationTerminalState $terminalState,
    ) {}

    public function handle(BulkOperationItemAttempt $attempt): BulkOperationItem
    {
        return DB::transaction(function () use ($attempt): BulkOperationItem {
            $evidence = BulkOperationItemAttempt::query()->findOrFail($attempt->id);
            $itemIdentity = BulkOperationItem::query()->findOrFail($evidence->bulk_operation_item_id);
            $operation = BulkOperation::query()->lockForUpdate()->findOrFail($itemIdentity->bulk_operation_id);
            $item = BulkOperationItem::query()->lockForUpdate()->findOrFail($itemIdentity->id);
            $terminalAttempt = BulkOperationItemAttempt::query()->findOrFail($evidence->id);
            if ($terminalAttempt->status === BulkAttemptStatus::Open) {
                throw new \LogicException('An open bulk attempt cannot be incorporated.');
            }
            if (($item->incorporated_attempt_generation ?? 0) >= $terminalAttempt->generation) {
                return $item;
            }
            $failureCount = BulkOperationItemAttempt::query()
                ->where('bulk_operation_item_id', $item->id)
                ->whereIn('status', [
                    BulkAttemptStatus::FailedRetryable->value,
                    BulkAttemptStatus::FailedPermanent->value,
                    BulkAttemptStatus::Abandoned->value,
                ])->count();

            [$status, $reason] = match ($terminalAttempt->status) {
                BulkAttemptStatus::Succeeded => [BulkItemStatus::Succeeded, null],
                BulkAttemptStatus::NotApplied => [BulkItemStatus::Skipped, $terminalAttempt->not_applied_reason],
                BulkAttemptStatus::FailedPermanent => [BulkItemStatus::FailedPermanent, $this->terminalReason($terminalAttempt)],
                BulkAttemptStatus::FailedRetryable, BulkAttemptStatus::Abandoned => $failureCount >= (int) config('bulk_operations.retry_ceiling')
                    ? [BulkItemStatus::FailedPermanent, 'retry_ceiling_exhausted']
                    : [BulkItemStatus::FailedRetryable, null],
                default => throw new \LogicException('Unsupported terminal bulk-attempt state.'),
            };

            $audit = null;
            if ($status !== BulkItemStatus::FailedRetryable) {
                $audit = $this->audit->record($operation, 'bulk_operation.item_finalized', 'laravel.bulk-worker', [
                    'attempt_generation' => $terminalAttempt->generation,
                    'attempt_status' => $terminalAttempt->status->value,
                    'item_status' => $status->value,
                    'operation_type' => $operation->operation_type->value,
                    'terminal_reason' => $reason,
                ], $item, $terminalAttempt);
            }

            $values = [
                'execution_status' => $status,
                'started_at' => $item->started_at ?? $terminalAttempt->started_at,
                'completed_at' => $status === BulkItemStatus::FailedRetryable ? null : now(),
                'terminal_reason' => $reason,
                'audit_event_id' => $audit?->id,
                'incorporated_attempt_generation' => $terminalAttempt->generation,
            ];
            if ($status === BulkItemStatus::Succeeded && $item->operation_type->value === 'bulk_promotion') {
                $values['result_identity'] = $terminalAttempt->result_identity_value;
            }
            $item->forceFill($values)->save();
            $this->convergeLockedParent($operation);

            return $item->refresh();
        }, 3);
    }

    public function convergeParent(BulkOperation $operation): BulkOperation
    {
        $parent = BulkOperation::query()->lockForUpdate()->findOrFail($operation->id);

        return $this->convergeLockedParent($parent);
    }

    public function convergeLockedParent(BulkOperation $parent): BulkOperation
    {
        $counts = BulkOperationItem::query()->where('bulk_operation_id', $parent->id)
            ->selectRaw('execution_status, COUNT(*) AS aggregate')->groupBy('execution_status')
            ->pluck('aggregate', 'execution_status')->map(fn ($count): int => (int) $count)->all();
        $resolved = $this->terminalState->handle(
            'succeeded',
            array_sum($counts),
            $parent->cancellation_requested_at !== null,
            $counts,
        );
        if ($resolved !== null && ! $parent->status->isTerminal()) {
            $parent->forceFill(['status' => $resolved])->save();
            $this->audit->record($parent, 'bulk_operation.converged', 'laravel.bulk-worker', [
                'operation_type' => $parent->operation_type->value,
                'terminal_status' => $resolved->value,
            ]);
        }

        return $parent;
    }

    private function terminalReason(BulkOperationItemAttempt $attempt): string
    {
        return match ($attempt->failure_category) {
            'promotion_conflict', 'promotion_technical_failure', 'promotion_abandoned_externally',
            'promotion_expired', 'full_ingestion_failed', 'authorization_insufficient' => $attempt->failure_category,
            default => 'retry_ceiling_exhausted',
        };
    }
}
