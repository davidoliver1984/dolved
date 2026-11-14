<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use Carbon\CarbonImmutable;

/** Syntactic inventory, deliberately not a temporal-role classifier. */
final class ExplicitTemporalReferences
{
    public static function forNeed(array $need): array
    {
        $references = [];
        $months = 'January|February|March|April|May|June|July|August|September|October|November|December';
        foreach ([$need['question_span'], ...($need['context_spans'] ?? [])] as $span) {
            preg_match_all('/(?<![\pL\d])(?:\d{4}-\d{2}-\d{2}|\d{1,2}\s+(?:'.$months.')\s+\d{4})(?![\pL\d])/iu', $span['quote'], $matches);
            foreach ($matches[0] as $quote) {
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
                    'at' => $at->toIso8601String()];
            }
        }

        return array_values($references);
    }
}
