<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class DocumentFamilyOwnerChangeException extends DomainException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
