<?php

declare(strict_types=1);

namespace App\Enums;

enum ChecksumVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Unavailable = 'unavailable';
}
