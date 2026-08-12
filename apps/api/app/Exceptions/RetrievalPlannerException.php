<?php

declare(strict_types=1);

namespace App\Exceptions;

final class RetrievalPlannerException extends RetrievalException
{
    public function __construct(
        string $message,
        public readonly string $category,
        public readonly ?int $providerStatus,
        public readonly int $attemptCount,
        public readonly bool $systemic,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
