<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentFamilyDeletionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case PartiallyFailed = 'partially_failed';
}
