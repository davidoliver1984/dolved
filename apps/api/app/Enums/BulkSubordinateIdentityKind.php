<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkSubordinateIdentityKind: string
{
    case PublicId = 'public_id';
    case EventId = 'event_id';
}
