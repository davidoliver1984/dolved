<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Models\Document;
use App\Support\Documents\DocumentAuthorityTimeline;
use Carbon\CarbonImmutable;

/** A resolved application fact for generation, not a request to reinterpret source prose. */
final readonly class ResolvedEvidenceTemporalStatus
{
    public function __construct(private DocumentAuthorityTimeline $timeline) {}

    /** @return array{evaluated_at: string, authority_start: ?string, authoritative_at_evaluated_at: bool} */
    public function forDocument(Document $document, CarbonImmutable $evaluatedAt): array
    {
        $document->loadMissing('family');

        return [
            'evaluated_at' => $evaluatedAt->toIso8601String(),
            'authority_start' => $this->timeline->authorityStart($document)?->toIso8601String(),
            'authoritative_at_evaluated_at' => $this->timeline->resolve($document->family, $evaluatedAt)?->id === $document->id,
        ];
    }
}
