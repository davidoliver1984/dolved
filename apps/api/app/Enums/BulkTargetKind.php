<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkTargetKind: string
{
    case Family = 'family';
    case Version = 'version';
    case ImportItem = 'import_item';
}
