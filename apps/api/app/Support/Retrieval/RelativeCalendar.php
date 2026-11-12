<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Mirror Python relative_calendar: explicit London calendar periods only. */
final class RelativeCalendar
{
    public const PATTERN = '/\b(last|this)\s+(month|quarter|year)\b/i';

    public static function period(string $quote, ?string $evaluatedAt): ?array
    {
        preg_match_all(self::PATTERN, $quote, $matches, PREG_SET_ORDER);
        if ($matches === []) {
            return null;
        }
        $relations = array_unique(array_map(fn ($m) => strtolower($m[1].' '.$m[2]), $matches));
        if (count($relations) !== 1 || $evaluatedAt === null
            || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $evaluatedAt) !== 1) {
            throw new InvalidArgumentException('Relative calendar reference requires one anchored period.');
        }
        [$relation, $unit] = explode(' ', array_values($relations)[0]);
        $local = CarbonImmutable::parse($evaluatedAt)->setTimezone('Europe/London');
        $start = match ($unit) {
            'month' => $local->startOfMonth(),
            'quarter' => $local->startOfQuarter(),
            'year' => $local->startOfYear(),
        };
        $width = ['month' => 1, 'quarter' => 3, 'year' => 12][$unit];
        if ($relation === 'last') {
            $start = $start->subMonths($width);
        }
        $end = $start->addMonths($width);

        return ['precision' => ['month' => 'month', 'quarter' => 'interval', 'year' => 'year'][$unit],
            'value' => match ($unit) {
                'month' => $start->format('Y-m'),
                'year' => $start->format('Y'),
                'quarter' => $start->toDateString().'/'.$end->subDay()->toDateString(),
            },
            'start_inclusive' => $start->toIso8601String(), 'end_exclusive' => $end->toIso8601String(),
            'timezone' => 'Europe/London'];
    }
}
