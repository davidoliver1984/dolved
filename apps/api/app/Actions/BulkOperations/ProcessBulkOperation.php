<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkAttemptStatus;
use App\Enums\BulkItemStatus;
use App\Jobs\ExecuteBulkOperation;
use App\Models\BulkOperation;
use App\Models\BulkOperationItemAttempt;

final readonly class ProcessBulkOperation
{
    public function __construct(
        private ReclaimExpiredBulkAttempts $reclaim,
        private ReconcileBulkOperationSubordinates $subordinates,
        private ConvergeBulkOperationCancellation $cancellation,
        private FinalizeBulkOperationAttempt $finalize,
        private ClaimBulkOperationItem $claim,
        private ExecuteBulkOperationItem $execute,
    ) {}

    public function handle(BulkOperation $operation, int $limit = 25): BulkOperation
    {
        $this->reclaim->handle($limit);
        $this->subordinates->handle($operation, $limit);
        $this->incorporatePending($operation, $limit);
        $this->cancellation->handle($operation);

        for ($processed = 0; $processed < $limit; $processed++) {
            $attempt = $this->claim->handle($operation, 'laravel.bulk-worker');
            if (! $attempt instanceof BulkOperationItemAttempt) {
                break;
            }
            $this->execute->handle($attempt);
        }

        $operation = $operation->refresh();
        if (! $operation->status->isTerminal() && $this->hasPendingWork($operation)) {
            ExecuteBulkOperation::dispatch($operation->id)->delay(now()->addSecond());
        }

        return $operation;
    }

    private function incorporatePending(BulkOperation $operation, int $limit): void
    {
        $attempts = BulkOperationItemAttempt::query()
            ->join('bulk_operation_items as item', 'item.id', '=', 'bulk_operation_item_attempts.bulk_operation_item_id')
            ->select('bulk_operation_item_attempts.*')
            ->where('item.bulk_operation_id', $operation->id)
            ->whereRaw('bulk_operation_item_attempts.generation > COALESCE(item.incorporated_attempt_generation, 0)')
            ->where('bulk_operation_item_attempts.status', '<>', BulkAttemptStatus::Open->value)
            ->orderBy('bulk_operation_item_attempts.id')->limit($limit)->get();
        foreach ($attempts as $attempt) {
            $this->finalize->handle($attempt);
        }
    }

    private function hasPendingWork(BulkOperation $operation): bool
    {
        return $operation->items()->whereIn('execution_status', [
            BulkItemStatus::Eligible->value,
            BulkItemStatus::FailedRetryable->value,
            BulkItemStatus::WaitingOnSubordinate->value,
        ])->exists();
    }
}
