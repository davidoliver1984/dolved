<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentGovernanceActorType: string
{
    case Human = 'human';
    case System = 'system';
}
