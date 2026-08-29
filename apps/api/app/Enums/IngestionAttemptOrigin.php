<?php

declare(strict_types=1);

namespace App\Enums;

enum IngestionAttemptOrigin: string
{
    case Ingestion = 'ingestion';
    case ContentClone = 'content_clone';
}
