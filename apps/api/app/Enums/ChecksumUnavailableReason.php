<?php

declare(strict_types=1);

namespace App\Enums;

enum ChecksumUnavailableReason: string
{
    case SourceMissing = 'source_missing';
    case SourceDeleted = 'source_deleted';
    case SourceUnrecoverable = 'source_unrecoverable';
}
