<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkAttemptSuccessKind: string
{
    case DatabaseLocal = 'database_local';
    case SubordinateInitiated = 'subordinate_initiated';
}
