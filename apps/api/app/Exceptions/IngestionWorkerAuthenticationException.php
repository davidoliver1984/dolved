<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class IngestionWorkerAuthenticationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('The ingestion worker request is not authenticated.');
    }
}
