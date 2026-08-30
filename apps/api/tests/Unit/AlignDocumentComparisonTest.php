<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Documents\AlignDocumentComparison;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AlignDocumentComparisonTest extends TestCase
{
    #[Test]
    public function it_aligns_insertions_modifications_and_moves_without_equal_ordinal_assumptions(): void
    {
        $before = [
            $this->element('a', 1, 'heading', 'Safety'),
            $this->element('b', 2, 'paragraph', 'Record the omission.'),
            $this->element('c', 3, 'paragraph', 'Notify the manager.'),
            $this->element('d', 4, 'paragraph', 'Review the event.'),
        ];
        $after = [
            $this->element('e', 1, 'heading', 'Safety'),
            $this->element('f', 2, 'paragraph', 'Assess immediate safety.'),
            $this->element('g', 3, 'paragraph', 'Record the omission immediately.'),
            $this->element('h', 4, 'paragraph', 'Review the event.'),
            $this->element('i', 5, 'paragraph', 'Notify the manager.'),
        ];

        $result = (new AlignDocumentComparison)->handle($before, $after, true, false);

        self::assertSame('reliable', $result['alignment_status']);
        self::assertSame(1, $result['change_counts']['added']);
        self::assertSame(1, $result['change_counts']['modified']);
        self::assertSame(1, $result['change_counts']['moved']);
        self::assertSame(2, $result['change_counts']['unchanged']);
        self::assertSame('unavailable', $result['formatting_comparison']);
        self::assertContains('Safety', array_column($result['differences'], 'section'));
    }

    #[Test]
    public function it_reports_partial_and_unavailable_alignment_truthfully(): void
    {
        $elements = [$this->element('a', 1, 'paragraph', 'Text')];
        $partial = (new AlignDocumentComparison)->handle($elements, $elements, true, true);
        self::assertSame('partial', $partial['alignment_status']);

        $unavailable = (new AlignDocumentComparison)->handle($elements, [], false, false);
        self::assertSame('unavailable', $unavailable['alignment_status']);
        self::assertSame([], $unavailable['differences']);
    }

    /** @return array<string, mixed> */
    private function element(string $id, int $ordinal, string $kind, string $text): array
    {
        return ['id' => $id, 'ordinal' => $ordinal, 'kind' => $kind, 'text' => $text];
    }
}
