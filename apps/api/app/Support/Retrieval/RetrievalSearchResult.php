<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

final readonly class RetrievalSearchResult
{
    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $lineage
     * @param  list<array<string, mixed>>  $diagnostics
     * @param  list<array<string, mixed>>  $usage
     */
    public function __construct(
        public array $candidates,
        public array $lineage,
        public array $diagnostics = [],
        public array $usage = [],
    ) {}
}
