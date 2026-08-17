<?php

declare(strict_types=1);

namespace App\Exceptions;

final class GenerationContextBudgetExceeded extends GenerationException
{
    public function __construct(
        public readonly string $policyVersion,
        public readonly int $proposedUnits,
        public readonly int $maximumUnits,
    ) {
        parent::__construct('The authorised generation context exceeds the configured structural budget.');
    }
}
