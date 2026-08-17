<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\RetrievalResult;

final readonly class GenerationAssemblyInput
{
    /** @param array<string, mixed> $resolvedTemporalAuthority @param array<string, mixed> $resolvedApplicabilityLocation @param array<string, mixed> $correlationLineage */
    public function __construct(
        public string $question,
        public RetrievalResult $retrievalResult,
        public array $resolvedTemporalAuthority,
        public array $resolvedApplicabilityLocation,
        public AuthorisedKnowledgeScope $authorisedScope,
        public array $correlationLineage,
    ) {}
}
