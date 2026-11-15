<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CoverageSelectionFailure extends RetrievalException
{
    public function __construct(public readonly string $reason, public readonly bool $runStopRequired,
        public readonly array $usage = [])
    {
        parent::__construct($reason);
    }
}
