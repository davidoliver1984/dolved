<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

final readonly class RetrievalSearchResult
{
    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $lineage
     */
    public function __construct(
        public array $candidates,
        public array $lineage,
    ) {}
}
