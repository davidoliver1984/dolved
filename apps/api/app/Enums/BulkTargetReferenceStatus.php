<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkTargetReferenceStatus: string
{
    case Live = 'live';
    case TargetDeleted = 'target_deleted';
}
