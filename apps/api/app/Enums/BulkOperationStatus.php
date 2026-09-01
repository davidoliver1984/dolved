<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkOperationStatus: string
{
    case PreparingMembership = 'preparing_membership';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithExclusions = 'completed_with_exclusions';
    case CompletedWithExceptions = 'completed_with_exceptions';
    case Cancelled = 'cancelled';
    case CancelledAfterPartialExecution = 'cancelled_after_partial_execution';
    case FailedBeforeExecution = 'failed_before_execution';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::CompletedWithExclusions,
            self::CompletedWithExceptions,
            self::Cancelled,
            self::CancelledAfterPartialExecution,
            self::FailedBeforeExecution,
        ], true);
    }
}
