<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportMatchStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
}
