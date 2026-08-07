<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Models\Document;
use App\Models\DocumentFamily;
use App\Support\Documents\DocumentAuthorityTimeline;
use Carbon\CarbonInterface;

final readonly class ResolveAuthoritativeDocument
{
    public function __construct(private DocumentAuthorityTimeline $timeline) {}

    public function current(DocumentFamily $family): ?Document
    {
        return $this->timeline->resolve($family, now());
    }

    public function validAtDate(DocumentFamily $family, CarbonInterface $validAt): ?Document
    {
        return $this->timeline->resolve($family, $validAt);
    }
}
