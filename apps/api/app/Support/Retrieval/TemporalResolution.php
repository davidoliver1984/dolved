<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\EligibilityClarificationReason;
use App\Models\Document;

final readonly class TemporalResolution
{
    private function __construct(
        public ?Document $document,
        public ?EligibilityClarificationReason $reason,
    ) {}

    public static function found(Document $document): self
    {
        return new self($document, null);
    }

    public static function unresolved(EligibilityClarificationReason $reason): self
    {
        return new self(null, $reason);
    }

    public static function empty(): self
    {
        return new self(null, null);
    }
}
