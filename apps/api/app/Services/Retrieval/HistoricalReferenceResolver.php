<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\EligibilityClarificationReason;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\TemporalResolution;
use Carbon\CarbonImmutable;

final readonly class HistoricalReferenceResolver
{
    public function __construct(
        private DocumentAuthorityTimeline $timeline,
        private TemporalPeriodResolver $periods,
    ) {}

    public function resolve(
        DocumentFamily $family,
        string $reference,
        CarbonImmutable $evaluatedAt,
    ): TemporalResolution {
        $value = mb_strtolower(trim($reference));
        preg_match_all('/\bversion\s+(\d+)\b/u', $value, $versionMatches);
        preg_match_all('/\b(\d{4})\b/u', $value, $yearMatches);
        $hasRelative = preg_match('/\b(old|older|previous|prior)\b/u', $value) === 1;
        $hasWithdrawal = str_contains($value, 'withdraw');
        $hasVersion = $versionMatches[1] !== [];
        $strategies = (int) $hasVersion + (int) ($yearMatches[1] !== [])
            + (int) $hasRelative + (int) ($hasWithdrawal && ! $hasVersion);
        if ($strategies !== 1 || count(array_unique($versionMatches[1])) > 1 || count(array_unique($yearMatches[1])) > 1) {
            return TemporalResolution::unresolved(
                EligibilityClarificationReason::AmbiguousHistoricalReference,
            );
        }

        $attained = $this->timeline->attainedVersions($family);
        if ($versionMatches[1] !== []) {
            $position = (int) $versionMatches[1][0] - 1;
            $document = $attained->get($position);

            return $document instanceof Document
                ? TemporalResolution::found($document)
                : TemporalResolution::unresolved(EligibilityClarificationReason::HistoricalReferenceUnresolved);
        }
        if ($yearMatches[1] !== []) {
            $resolution = $this->periods->resolve($family, $yearMatches[1][0]);
            if ($resolution->reason === EligibilityClarificationReason::UnresolvableTemporalPeriod) {
                return TemporalResolution::unresolved(EligibilityClarificationReason::HistoricalReferenceUnresolved);
            }

            return $resolution;
        }
        if ($hasWithdrawal) {
            $withdrawn = $attained->filter(
                fn (Document $document): bool => $document->withdrawn_at !== null
                    && $document->withdrawn_at->lte($evaluatedAt),
            )->values();
            if ($withdrawn->count() > 1) {
                return TemporalResolution::unresolved(EligibilityClarificationReason::AmbiguousHistoricalReference);
            }
            $document = $withdrawn->first();

            return $document instanceof Document
                ? TemporalResolution::found($document)
                : TemporalResolution::unresolved(EligibilityClarificationReason::HistoricalReferenceUnresolved);
        }
        if ($hasRelative) {
            $current = $this->timeline->resolve($family, $evaluatedAt);
            $position = $current === null
                ? false
                : $attained->search(fn (Document $document): bool => $document->is($current));
            $document = is_int($position) && $position > 0 ? $attained->get($position - 1) : null;

            return $document instanceof Document
                ? TemporalResolution::found($document)
                : TemporalResolution::unresolved(EligibilityClarificationReason::HistoricalReferenceUnresolved);
        }

        return TemporalResolution::unresolved(EligibilityClarificationReason::HistoricalReferenceUnresolved);
    }
}
