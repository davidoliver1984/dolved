<?php

declare(strict_types=1);

namespace App\Enums;

enum ExtractionUploadCleanupState: string
{
    case NotNeeded = 'not_needed';
    case Eligible = 'eligible';
    case Claimed = 'claimed';
    case Deleted = 'deleted';
    case Failed = 'failed';
}
