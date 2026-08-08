<?php

declare(strict_types=1);

namespace App\Enums;

enum EvidenceThresholdPolicyStatus: string
{
    case Calibrating = 'calibrating';
    case Active = 'active';
    case Retired = 'retired';
}
