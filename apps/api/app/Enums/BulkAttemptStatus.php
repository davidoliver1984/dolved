<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkAttemptStatus: string
{
    case Open = 'open';
    case Succeeded = 'succeeded';
    case NotApplied = 'not_applied';
    case FailedRetryable = 'failed_retryable';
    case FailedPermanent = 'failed_permanent';
    case Abandoned = 'abandoned';
}
