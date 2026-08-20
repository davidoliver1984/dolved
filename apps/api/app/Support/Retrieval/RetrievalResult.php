<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\RetrievalClarificationSource;
use App\Enums\RetrievalOutcome;

final readonly class RetrievalResult
{
    /** @param list<array<string, mixed>> $candidates @param array<string, mixed> $resolvedTemporalAuthority @param array<string, mixed> $resolvedApplicabilityLocation @param array<string, mixed> $lineage @param list<array<string, mixed>> $usage */
    public function __construct(
        public RetrievalOutcome $outcome,
        public array $candidates = [],
        public ?string $reason = null,
        public ?RetrievalClarificationSource $clarificationSource = null,
        public array $resolvedTemporalAuthority = [],
        public array $resolvedApplicabilityLocation = [],
        public array $lineage = [],
        public array $usage = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'candidates' => $this->candidates,
            'reason' => $this->reason,
            'clarification_source' => $this->clarificationSource?->value,
            'resolved_temporal_authority' => $this->resolvedTemporalAuthority,
            'resolved_applicability_location' => $this->resolvedApplicabilityLocation,
            'lineage' => $this->lineage,
            'usage' => $this->usage,
        ];
    }
}
