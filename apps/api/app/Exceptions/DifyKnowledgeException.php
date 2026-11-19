<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DifyKnowledgeException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The Dify knowledge operation could not be completed.');
    }
}
