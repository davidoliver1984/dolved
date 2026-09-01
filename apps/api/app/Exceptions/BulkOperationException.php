<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class BulkOperationException extends RuntimeException
{
    public static function idempotencyConflict(): self
    {
        return new self('The idempotency key is already bound to a different bulk request.', 409);
    }

    public static function selectionTooLarge(int $maximum): self
    {
        return new self("The selection exceeds the maximum of {$maximum} targets. Narrow the filters or select fewer rows.", 422);
    }

    public static function targetNotFound(): self
    {
        return new self('The selected bulk-operation target was not found.', 404);
    }
}
