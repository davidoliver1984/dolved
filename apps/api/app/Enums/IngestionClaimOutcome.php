<?php

declare(strict_types=1);

namespace App\Enums;

enum IngestionClaimOutcome: string
{
    case Claimed = 'claimed';
    case AlreadyClaimed = 'already_claimed';
    case OwnedByAnotherWorker = 'owned_by_another_worker';
    case Reclaimed = 'reclaimed';
    case AlreadyCompleted = 'already_completed';
    case PermanentlyFailed = 'permanently_failed';
    case StaleEvent = 'stale_event';
    case IneligibleState = 'ineligible_state';
}
