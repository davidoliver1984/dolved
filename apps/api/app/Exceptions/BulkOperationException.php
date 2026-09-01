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

    public static function confirmationActorMismatch(): self
    {
        return new self('Only the initiating actor may confirm this bulk operation.', 403);
    }

    public static function notConfirmable(): self
    {
        return new self('This bulk operation can no longer be confirmed.', 409);
    }

    public static function notCancellable(): self
    {
        return new self('This bulk operation is already terminal.', 409);
    }
}
