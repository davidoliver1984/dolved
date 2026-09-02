<?php

declare(strict_types=1);

namespace App\Enums;

enum GovernanceEmailAttemptStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case FailedRetryable = 'failed_retryable';
    case FailedPermanent = 'failed_permanent';
    case Abandoned = 'abandoned';
}
