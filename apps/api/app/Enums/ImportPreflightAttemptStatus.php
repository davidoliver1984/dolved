<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportPreflightAttemptStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
