<?php

declare(strict_types=1);

namespace App\Support\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\IngestionClaimOutcome;

final readonly class IngestionClaimResult
{
    public function __construct(
        public IngestionClaimOutcome $outcome,
        public ?DocumentStatus $documentStatus,
    ) {}
}
