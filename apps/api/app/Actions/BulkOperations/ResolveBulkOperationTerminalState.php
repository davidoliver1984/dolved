<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkOperationStatus;
use LogicException;

final class ResolveBulkOperationTerminalState
{
    /**
     * @param  array<string, int>  $distribution
     */
    public function handle(
        string $freezeOutcome,
        int $totalItemCount,
        bool $cancellationRequested,
        array $distribution,
    ): ?BulkOperationStatus {
        if ($freezeOutcome === 'failed') {
            return BulkOperationStatus::FailedBeforeExecution;
        }
        if ($freezeOutcome !== 'succeeded' || $totalItemCount < 0) {
            throw new LogicException('Invalid bulk terminal-state input.');
        }

        $count = static fn (string $state): int => (int) ($distribution[$state] ?? 0);
        if ($totalItemCount === 0) {
            return BulkOperationStatus::CompletedWithExclusions;
        }
        $nonTerminal = $count('eligible') + $count('failed_retryable') + $count('waiting_on_subordinate');
        if ($nonTerminal > 0) {
            return null;
        }

        if ($cancellationRequested) {
            $performed = $count('succeeded') + $count('skipped') + $count('failed_permanent');

            return $performed === 0
                ? BulkOperationStatus::Cancelled
                : BulkOperationStatus::CancelledAfterPartialExecution;
        }
        if ($count('skipped') > 0 || $count('failed_permanent') > 0 || $count('cancelled') > 0) {
            return BulkOperationStatus::CompletedWithExceptions;
        }
        if ($count('excluded') > 0) {
            return BulkOperationStatus::CompletedWithExclusions;
        }

        return BulkOperationStatus::Completed;
    }
}
