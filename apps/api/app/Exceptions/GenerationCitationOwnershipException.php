<?php

declare(strict_types=1);

namespace App\Exceptions;

final class GenerationCitationOwnershipException extends GenerationException
{
    public function __construct(public readonly array $ownership)
    {
        parent::__construct('Answer cites another need evidence.');
    }
}
