<?php

declare(strict_types=1);

namespace App\Enums;

enum GovernanceEmailEnvelopeStatus: string
{
    case Assembling = 'assembling';
    case Ready = 'ready';
    case Dispatching = 'dispatching';
    case Sent = 'sent';
    case FailedPermanent = 'failed_permanent';
    case Suppressed = 'suppressed';
}
