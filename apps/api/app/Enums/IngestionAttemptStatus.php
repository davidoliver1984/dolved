<?php

declare(strict_types=1);

namespace App\Enums;

enum IngestionAttemptStatus: string
{
    case Open = 'open';
    case Sealed = 'sealed';
    case PublicationAuthorised = 'publication_authorised';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
