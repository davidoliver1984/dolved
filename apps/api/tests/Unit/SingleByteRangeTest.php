<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Documents\SingleByteRange;
use App\Services\Documents\UnsatisfiableByteRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SingleByteRangeTest extends TestCase
{
    /** @return array<string, array{string, int, int, int}> */
    public static function validRanges(): array
    {
        return [
            'closed' => ['bytes=2-4', 10, 2, 4],
            'clamped end' => ['bytes=2-99', 10, 2, 9],
            'open end' => ['bytes=7-', 10, 7, 9],
            'suffix' => ['bytes=-3', 10, 7, 9],
            'large suffix' => ['bytes=-99', 10, 0, 9],
        ];
    }

    #[DataProvider('validRanges')]
    public function test_it_parses_the_complete_single_range_grammar(string $header, int $length, int $start, int $end): void
    {
        $range = SingleByteRange::parse($header, $length);

        $this->assertNotNull($range);
        $this->assertSame($start, $range->start);
        $this->assertSame($end, $range->end);
        $this->assertSame($end - $start + 1, $range->length());
    }

    /** @return array<string, array{string, int}> */
    public static function invalidRanges(): array
    {
        return [
            'reverse' => ['bytes=4-2', 10],
            'past end' => ['bytes=10-', 10],
            'zero suffix' => ['bytes=-0', 10],
            'multiple' => ['bytes=0-1,4-5', 10],
            'unit' => ['items=0-1', 10],
            'empty object' => ['bytes=0-', 0],
            'whitespace' => ['bytes= 0-1', 10],
        ];
    }

    #[DataProvider('invalidRanges')]
    public function test_it_rejects_invalid_or_unsatisfiable_ranges(string $header, int $length): void
    {
        $this->expectException(UnsatisfiableByteRange::class);

        SingleByteRange::parse($header, $length);
    }
}
