<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class GovernanceEmailTransportException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
