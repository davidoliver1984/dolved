<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkEligibilityStatus: string
{
    case Eligible = 'eligible';
    case Excluded = 'excluded';
}
