<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\EligibilityClarificationReason;
use App\Models\DocumentFamily;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\RelativeCalendar;
use App\Support\Retrieval\TemporalResolution;
use Carbon\CarbonImmutable;

final readonly class TemporalPeriodResolver
{
    public function __construct(private DocumentAuthorityTimeline $timeline) {}

    public function resolve(DocumentFamily $family, string $reference, ?string $sourceQuote = null): TemporalResolution
    {
        $window = $this->window($reference, $sourceQuote);
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

        $narrowed = $this->qualifiedYearWindow($reference, $sourceQuote);
        if ($narrowed !== null) {
            // Only retry the same authority check on the quote-bounded period.
            // No invented exact date, and no choosing one side of a boundary.
            return $this->resolve($family, $narrowed);
        }

        return TemporalResolution::unresolved(
            EligibilityClarificationReason::AmbiguousAuthorityWindowForPeriod,
        );
    }

    public function qualifiedYearWindow(string $reference, ?string $sourceQuote): ?string
    {
        if (preg_match('/^\d{4}$/D', $reference) !== 1 || $sourceQuote === null) {
            return null;
        }
        $year = preg_quote($reference, '/');
        $first = preg_match('/\b(?:early|start of|beginning of|first half of)\s+'.$year.'\b/i', $sourceQuote) === 1;
        $last = preg_match('/\b(?:late|end of|second half of|latter half of)\s+'.$year.'\b/i', $sourceQuote) === 1;
        if ($first === $last) {
            return null;
        }

        // A deliberately broad half-year: if authority changes within it,
        // clarification remains mandatory rather than guessing a narrower day.
        return $first ? $reference.'-01-01/'.$reference.'-06-30' : $reference.'-07-01/'.$reference.'-12-31';
    }

    public function boundaryStart(string $reference): ?CarbonImmutable
    {
        $value = trim($reference);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1) {
            $exact = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            if ($exact !== false && $exact->format('Y-m-d') === $value) {
                return $exact;
            }
        }

        return $this->window($reference)[0] ?? null;
    }

    /** Adjacent boundary semantics are application-owned; no model-selected version. */
    public function adjacentBoundary(string $reference, string $relation, ?string $sourceQuote = null): ?CarbonImmutable
    {
        if (! in_array($relation, ['before', 'after'], true)) {
            return null;
        }
        $window = $this->window($reference, $sourceQuote);
        if ($window === null && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $reference) === 1) {
            $start = $this->boundaryStart($reference);
            $window = $start === null ? null : [$start, $start->addDay()];
        }

        return $window === null ? null : ($relation === 'before' ? $window[0]->subMicrosecond() : $window[1]);
    }

    public function resolveAdjacent(DocumentFamily $family, string $reference, string $relation, ?string $sourceQuote = null): TemporalResolution
    {
        $at = $this->adjacentBoundary($reference, $relation, $sourceQuote);
        if ($at === null) {
            return TemporalResolution::unresolved(EligibilityClarificationReason::UnresolvableTemporalPeriod);
        }
        $document = $this->timeline->resolve($family, $at);

        return $document === null ? TemporalResolution::empty() : TemporalResolution::found($document);
    }

    /** @return array{CarbonImmutable, CarbonImmutable}|null */
    public function window(string $reference, ?string $sourceQuote = null): ?array
    {
        $value = trim($reference);
        $zone = $sourceQuote !== null && preg_match(RelativeCalendar::PATTERN, $sourceQuote) === 1 ? 'Europe/London' : null;
        if (preg_match('/^\d{4}-\d{2}$/D', $value) === 1) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $value, $zone);

            return $start !== false && $start->format('Y-m') === $value ? [$start, $start->addMonth()] : null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\/(\d{4}-\d{2}-\d{2})$/D', $value, $matches) === 1) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $matches[1], $zone);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $matches[2], $zone);

            return $start !== false && $end !== false && $start->format('Y-m-d') === $matches[1]
                && $end->format('Y-m-d') === $matches[2] && $start->lte($end)
                ? [$start, $end->addDay()] : null;
        }
        if (preg_match('/^(?:(?:in|during)\s+)?(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})$/i', $value, $matches) === 1) {
            $start = CarbonImmutable::createFromFormat('!F Y', ucfirst(strtolower($matches[1])).' '.$matches[2]);

            return $start === false ? null : [$start, $start->addMonth()];
        }
        if (preg_match('/^(?:(?:in|during)\s+)?(\d{4})$/i', $value, $matches) === 1) {
            $start = CarbonImmutable::createFromFormat('!Y', $matches[1], $zone);

            return $start === false ? null : [$start, $start->addYear()];
        }

        return null;
    }
}
