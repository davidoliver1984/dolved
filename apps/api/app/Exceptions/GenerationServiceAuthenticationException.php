<?php

declare(strict_types=1);

namespace App\Exceptions;

final class GenerationServiceAuthenticationException extends GenerationException
{
    public function __construct(public readonly bool $replayRejected)
    {
        parent::__construct('The generation service rejected caller authentication.');
    }
}
