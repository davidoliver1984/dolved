<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Expired = 'expired';
}
