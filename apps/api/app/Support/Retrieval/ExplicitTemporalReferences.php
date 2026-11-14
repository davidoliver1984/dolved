<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Services\Retrieval\TemporalPeriodResolver;
use Carbon\CarbonImmutable;

/** Syntactic inventory, deliberately not a temporal-role classifier. */
final class ExplicitTemporalReferences
{
    public static function forNeed(array $need): array
    {
        $references = [];
        $months = 'January|February|March|April|May|June|July|August|September|October|November|December';
        foreach ([$need['question_span'], ...($need['context_spans'] ?? [])] as $span) {
            // Longest form first consumes nested month/year tokens even when the
            // full date is invalid. Numeric punctuation/identifier boundaries
            // and duration units prevent treating measurements as calendar years.
            preg_match_all('/(?<![\pL\d_\/.:,+\-£$])(?:\d{4}-\d{2}-\d{2}|\d{1,2}\s+(?:'.$months.')\s+\d{4}|(?:'.$months.')\s+[1-9]\d{3}|[1-9]\d{3})(?![\pL\d_\/:+%\-]|[.,]\d)(?!\s*(?:years?|months?|weeks?|days?|hours?|minutes?|seconds?)\b)/iu', $span['quote'], $matches);
            foreach ($matches[0] as $quote) {
                $precision = preg_match('/^[1-9]\d{3}$/D', $quote) ? 'year'
                    : (preg_match('/^(?:'.$months.')\s+[1-9]\d{3}$/iu', $quote) ? 'month' : 'day');
                if ($precision !== 'day') {
                    $window = app(TemporalPeriodResolver::class)->window($quote);
                    // Shared datetime contracts cannot encode year 10000 as an
                    // exclusive end. Such periods are outside this bounded grammar.
                    if ($window !== null && $window[1]->year <= 9999) {
                        $references[$span['turn_id'].':'.$quote] = ['turn_id' => $span['turn_id'], 'quote' => $quote,
                            'at' => null, 'precision' => $precision, 'value' => $window[0]->format($precision === 'year' ? 'Y' : 'Y-m'),
                            'period_start' => $window[0]->toIso8601String(), 'period_end' => $window[1]->toIso8601String()];
                    }

                    continue;
                }
                $format = preg_match('/^\d{4}-/', $quote) ? '!Y-m-d' : '!j F Y';
                try {
                    // Match EvidenceNeedsPlan's existing explicit-date timezone;
                    // the separate relative-calendar London rule is unchanged.
                    $at = CarbonImmutable::createFromFormat($format, $quote);
                    if ($at === false || strcasecmp($at->format($format === '!Y-m-d' ? 'Y-m-d' : 'j F Y'), $quote) !== 0) {
                        continue;
                    }
                } catch (\Throwable) {
                    continue;
                }
                $key = $span['turn_id'].':'.$quote;
                $references[$key] = ['turn_id' => $span['turn_id'], 'quote' => $quote,
                    'at' => $at->toIso8601String(), 'precision' => 'day', 'value' => $at->format('Y-m-d'),
                    'period_start' => null, 'period_end' => null];
            }
        }

        return array_values($references);
    }
}
