<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\RetrievalOutcome;

final readonly class RetrievalResult
{
    /** @param list<array<string, mixed>> $candidates */
    public function __construct(
        public RetrievalOutcome $outcome,
        public array $candidates = [],
        public ?string $reason = null,
    ) {}

    /** @return array{outcome: string, candidates: list<array<string, mixed>>, reason: string|null} */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'candidates' => $this->candidates,
            'reason' => $this->reason,
        ];
    }
}
