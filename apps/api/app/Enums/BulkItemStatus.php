<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkItemStatus: string
{
    case Excluded = 'excluded';
    case Eligible = 'eligible';
    case FailedRetryable = 'failed_retryable';
    case WaitingOnSubordinate = 'waiting_on_subordinate';
    case Succeeded = 'succeeded';
    case FailedPermanent = 'failed_permanent';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
