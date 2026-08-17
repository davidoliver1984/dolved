<?php

declare(strict_types=1);

namespace App\Exceptions;

final class GenerationProviderException extends GenerationException
{
    public function __construct(
        public readonly string $category,
        public readonly ?string $provider,
        public readonly ?string $model,
        public readonly ?int $httpStatus,
        public readonly int $attemptCount,
        public readonly float $latencyMs,
    ) {
        parent::__construct('The generation provider failed with a typed operational error.');
    }
}
