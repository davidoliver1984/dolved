<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ImportPromotionException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function conflict(string $reason): self
    {
        return new self($reason, 'The import promotion cannot continue.');
    }

    public static function invalid(string $reason): self
    {
        return new self($reason, 'The import promotion request is invalid.');
    }
}
