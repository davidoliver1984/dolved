<?php

declare(strict_types=1);

namespace App\Enums;

enum IngestionClaimOutcome: string
{
    case Claimed = 'claimed';
    case AlreadyClaimed = 'already_claimed';
    case StaleEvent = 'stale_event';
    case IneligibleState = 'ineligible_state';
}
