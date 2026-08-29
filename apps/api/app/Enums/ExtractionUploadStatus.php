<?php

declare(strict_types=1);

namespace App\Enums;

enum ExtractionUploadStatus: string
{
    case Authorised = 'authorised';
    case Verified = 'verified';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
