<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Retrieval\RetrievalFailureObservation;

final class RetrievalExecutionException extends RetrievalException
{
    public function __construct(
        public readonly RetrievalFailureObservation $observation,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('Retrieval execution failed.', 0, $previous);
    }
}
