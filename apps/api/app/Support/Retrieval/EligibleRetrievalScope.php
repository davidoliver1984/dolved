<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\RetrievalClarificationSource;
use App\Enums\RetrievalOutcome;

final readonly class EligibleRetrievalScope
{
    /**
     * @param  array<string, list<string>>  $documentPublicIdsBySide
     */
    public function __construct(
        public RetrievalOutcome $outcome,
        public array $documentPublicIdsBySide = [],
        public ?string $reason = null,
        public ?string $resolvedLocationPublicId = null,
        public ?RetrievalClarificationSource $clarificationSource = null,
    ) {}

    public function canSearch(): bool
    {
        return $this->outcome === RetrievalOutcome::EvidenceFound;
    }
}
