<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\RetrievalOutcome;
use App\Support\Retrieval\NeedsEvidenceSelection;
use App\Support\Retrieval\RetrievalResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NeedsEvidenceSelectionTest extends TestCase
{
    private function candidate(string $id, string $family, string $side, int $rank): array
    {
        return ['chunk_id' => $id, 'document_id' => $family.'-'.$side,
            'document_family_id' => $family, 'side' => $side, 'rank' => $rank];
    }

    public function test_pair_and_current_support_survive_global_cap_and_descriptive_edge_has_no_effect(): void
    {
        $needs = [['need_id' => 'n1', 'operation' => 'compare'], ['need_id' => 'n2', 'operation' => 'retrieve']];
        $results = ['n1' => new RetrievalResult(RetrievalOutcome::EvidenceFound, [
            $this->candidate('p1', 'a', 'primary', 1), $this->candidate('p2', 'a', 'primary', 2),
            $this->candidate('c1', 'a', 'comparison', 1), $this->candidate('c2', 'a', 'comparison', 2),
            $this->candidate('bp', 'b', 'primary', 1), $this->candidate('bc', 'b', 'comparison', 1),
        ]), 'n2' => new RetrievalResult(RetrievalOutcome::EvidenceFound, [$this->candidate('support', 's', 'primary', 20)])];
        $selection = new NeedsEvidenceSelection;
        $selected = $selection->select($needs, $results);
        $this->assertLessThanOrEqual(5, count($selected));
        $this->assertContains('support', array_column($selected, 'chunk_id'));
        foreach (array_unique(array_column(array_filter($selected, fn ($c) => $c['need_id'] === 'n1'), 'document_family_id')) as $family) {
            $sides = array_unique(array_column(array_filter($selected, fn ($c) => $c['need_id'] === 'n1' && $c['document_family_id'] === $family), 'side'));
            sort($sides);
            $this->assertSame(['comparison', 'primary'], $sides);
        }
        $needs[1]['supports_need_id'] = 'n1';
        $this->assertSame($selected, $selection->select($needs, $results));
    }

    public function test_current_evidence_cannot_supply_a_missing_comparison_side(): void
    {
        $needs = [['need_id' => 'n1', 'operation' => 'compare'], ['need_id' => 'n2', 'operation' => 'retrieve']];
        $results = ['n1' => new RetrievalResult(RetrievalOutcome::EvidenceFound, [$this->candidate('p', 'a', 'primary', 1)]),
            'n2' => new RetrievalResult(RetrievalOutcome::EvidenceFound, [$this->candidate('s', 'a', 'comparison', 1)])];
        $selected = (new NeedsEvidenceSelection)->select($needs, $results);
        $this->assertSame(['n2'], array_column($selected, 'need_id'));
    }

    public function test_same_document_on_both_sides_cannot_form_a_pair(): void
    {
        $p = $this->candidate('p', 'a', 'primary', 1);
        $c = $this->candidate('c', 'a', 'comparison', 1);
        $c['document_id'] = $p['document_id'];
        $this->assertSame([], (new NeedsEvidenceSelection)->select([['need_id' => 'n1', 'operation' => 'compare']],
            ['n1' => new RetrievalResult(RetrievalOutcome::EvidenceFound, [$p, $c])]));
    }

    public function test_two_comparisons_preserve_both_pairs_within_k5(): void
    {
        $needs = [['need_id' => 'n1', 'operation' => 'compare'], ['need_id' => 'n2', 'operation' => 'compare']];
        $pair = [$this->candidate('p', 'a', 'primary', 1), $this->candidate('c', 'a', 'comparison', 1)];
        $selected = (new NeedsEvidenceSelection)->select($needs, [
            'n1' => new RetrievalResult(RetrievalOutcome::EvidenceFound, $pair),
            'n2' => new RetrievalResult(RetrievalOutcome::EvidenceFound, $pair),
        ]);
        $this->assertCount(4, $selected);
        foreach (['n1', 'n2'] as $id) {
            $this->assertSame(['primary', 'comparison'], array_column(array_filter($selected, fn ($c) => $c['need_id'] === $id), 'side'));
        }
    }

    public function test_three_needs_still_exceed_slice_fanout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NeedsEvidenceSelection)->select([['need_id' => 'n1', 'operation' => 'compare'], ['need_id' => 'n2', 'operation' => 'compare'], ['need_id' => 'n3', 'operation' => 'retrieve']], []);
    }

    public function test_pair_ranking_considers_both_sides_before_chunk_identity(): void
    {
        $needs = [['need_id' => 'n1', 'operation' => 'compare'], ['need_id' => 'n2', 'operation' => 'compare']];
        $candidates = [$this->candidate('z-primary-best', 'a', 'primary', 1),
            $this->candidate('a-primary-weaker', 'a', 'primary', 2),
            $this->candidate('z-comparison-best', 'a', 'comparison', 1),
            $this->candidate('a-comparison-weaker', 'a', 'comparison', 2)];
        $selected = (new NeedsEvidenceSelection)->select($needs, [
            'n1' => new RetrievalResult(RetrievalOutcome::EvidenceFound, $candidates),
            'n2' => new RetrievalResult(RetrievalOutcome::EvidenceFound, array_reverse($candidates)),
        ]);
        foreach (['n1', 'n2'] as $id) {
            $owned = array_column(array_filter($selected, fn ($c) => $c['need_id'] === $id), 'chunk_id');
            $this->assertContains('z-primary-best', $owned);
            $this->assertContains('z-comparison-best', $owned);
        }
        $this->assertLessThanOrEqual(5, count($selected));
    }
}
