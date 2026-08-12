<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\EligibilityClarificationReason;
use App\Models\DocumentFamily;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\TemporalResolution;
use Carbon\CarbonImmutable;

final readonly class TemporalPeriodResolver
{
    public function __construct(private DocumentAuthorityTimeline $timeline) {}

    public function resolve(DocumentFamily $family, string $reference): TemporalResolution
    {
        $window = $this->window($reference);
        if ($window === null) {
            return TemporalResolution::unresolved(
                EligibilityClarificationReason::UnresolvableTemporalPeriod,
            );
        }

        [$start, $end] = $window;
        $atStart = $this->timeline->resolve($family, $start);
        $beforeEnd = $this->timeline->resolve($family, $end->subMicrosecond());
        if ($atStart !== null && $beforeEnd !== null && $atStart->is($beforeEnd)) {
            return TemporalResolution::found($atStart);
        }
        if ($atStart === null && $beforeEnd === null) {
            return TemporalResolution::empty();
        }

        return TemporalResolution::unresolved(
            EligibilityClarificationReason::AmbiguousAuthorityWindowForPeriod,
        );
    }

    /** @return array{CarbonImmutable, CarbonImmutable}|null */
    private function window(string $reference): ?array
    {
        $value = trim($reference);
        if (preg_match('/^(?:(?:in|during)\s+)?(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})$/i', $value, $matches) === 1) {
            $start = CarbonImmutable::createFromFormat('!F Y', ucfirst(strtolower($matches[1])).' '.$matches[2]);

            return $start === false ? null : [$start, $start->addMonth()];
        }
        if (preg_match('/^(?:(?:in|during)\s+)?(\d{4})$/i', $value, $matches) === 1) {
            $start = CarbonImmutable::createFromFormat('!Y', $matches[1]);

            return $start === false ? null : [$start, $start->addYear()];
        }

        return null;
    }
}
