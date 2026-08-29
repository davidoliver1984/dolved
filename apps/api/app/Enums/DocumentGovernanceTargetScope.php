<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentGovernanceTargetScope: string
{
    case Family = 'family';
    case Version = 'version';
}
