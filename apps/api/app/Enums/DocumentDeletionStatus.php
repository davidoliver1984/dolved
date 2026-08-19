<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentDeletionStatus: string
{
    case AwaitingQuiescence = 'awaiting_quiescence';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
