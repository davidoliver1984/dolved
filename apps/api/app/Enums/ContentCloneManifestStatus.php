<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentCloneManifestStatus: string
{
    case Created = 'created';
    case Verified = 'verified';
    case Consumed = 'consumed';
    case Cancelled = 'cancelled';
}
