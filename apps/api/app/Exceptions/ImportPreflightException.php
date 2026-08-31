<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ImportPreflightException extends RuntimeException
{
    private function __construct(public readonly string $reason, public readonly int $status)
    {
        parent::__construct('The import preflight operation could not be accepted.');
    }

    public static function conflict(string $reason): self
    {
        return new self($reason, 409);
    }

    public static function invalid(string $reason): self
    {
        return new self($reason, 422);
    }
}
