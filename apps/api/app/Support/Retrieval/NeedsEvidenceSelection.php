<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use InvalidArgumentException;

/** Bounded coverage allocation; scores and authority are supplied by production retrieval. */
final class NeedsEvidenceSelection
{
    public const FINAL_K = 5;

    public const MAX_NEEDS = 3;

    public const MAX_SEARCH_LANES = 4;

    public static function searchLanes(array $needs): int
    {
        return array_sum(array_map(fn ($need) => match ($need['operation']) {
            'scope_enquiry' => 0,
            'compare' => 2,
            default => 1,
        }, $needs));
    }

    /** @param list<array<string, mixed>> $needs @param array<string, RetrievalResult> $results */
    public function select(array $needs, array $results): array
    {
        return $this->selectWithCoverage($needs, $results)['candidates'];
    }

    /** Counts refer to complete eligible units (a comparison unit is a pair), not source completeness. */
    public function selectWithCoverage(array $needs, array $results): array
    {
        if (count($needs) > self::MAX_NEEDS || self::searchLanes($needs) > self::MAX_SEARCH_LANES) {
            throw new InvalidArgumentException('The production slice permits three needs and four search lanes.');
        }
        $unitsByNeed = [];
        foreach ($needs as $need) {
            $id = $need['need_id'];
            $candidates = $results[$id]->candidates ?? [];
            usort($candidates, self::rank(...));
            $units = [];
            if ($need['operation'] === 'compare') {
                $families = [];
                foreach ($candidates as $candidate) {
                    $families[$candidate['document_family_id']][$candidate['side']][] = $candidate;
                }
                foreach ($families as $sides) {
                    foreach ($sides['primary'] ?? [] as $primary) {
                        foreach ($sides['comparison'] ?? [] as $comparison) {
                            if ($primary['document_id'] !== $comparison['document_id']) {
                                $units[] = [$primary, $comparison];
                            }
                        }
                    }
                }
            } else {
                $units = array_map(fn ($candidate) => [$candidate], $candidates);
            }
            usort($units, self::unitRank(...));
            $unitsByNeed[$id] = $units;
        }
        $selected = [];
        // Each answerable need gets its minimum evidence unit before spare capacity.
        foreach ($needs as $need) {
            $id = $need['need_id'];
            if (isset($unitsByNeed[$id][0])) {
                $this->includeUnit($selected, $id, $unitsByNeed[$id][0]);
            }
        }
        $remaining = [];
        foreach ($unitsByNeed as $id => $units) {
            foreach ($units as $unit) {
                $remaining[] = ['need_id' => $id, 'unit' => $unit];
            }
        }
        usort($remaining, fn ($a, $b) => self::unitRank($a['unit'], $b['unit']) ?: strcmp($a['need_id'], $b['need_id']));
        foreach ($remaining as $item) {
            $this->includeUnit($selected, $item['need_id'], $item['unit']);
        }

        $coverage = [];
        foreach ($unitsByNeed as $id => $units) {
            $included = count(array_filter($units, static function (array $unit) use ($selected, $id): bool {
                foreach ($unit as $candidate) {
                    if (! isset($selected[$id.':'.$candidate['side'].':'.$candidate['chunk_id']])) {
                        return false;
                    }
                }

                return true;
            }));
            $coverage[$id] = ['eligible_unit_count' => count($units),
                'selected_unit_count' => $included, 'budget_cut_unit_count' => count($units) - $included];
        }

        return ['candidates' => array_values($selected), 'coverage' => $coverage];
    }

    private function includeUnit(array &$selected, string $needId, array $unit): void
    {
        $additions = [];
        foreach ($unit as $candidate) {
            $key = $needId.':'.$candidate['side'].':'.$candidate['chunk_id'];
            if (! isset($selected[$key])) {
                $additions[$key] = [...$candidate, 'need_id' => $needId];
            }
        }
        if (count($selected) + count($additions) <= self::FINAL_K) {
            $selected += $additions;
        }
    }

    private static function rank(array $a, array $b): int
    {
        return ($a['rank'] <=> $b['rank']) ?: strcmp($a['chunk_id'], $b['chunk_id']);
    }

    private static function unitRank(array $a, array $b): int
    {
        $rank = min(array_column($a, 'rank')) <=> min(array_column($b, 'rank'));
        // Among equally strong leading candidates, retain the stronger companion
        // before using identity as a deterministic final tie-break.
        $rank = $rank ?: max(array_column($a, 'rank')) <=> max(array_column($b, 'rank'));

        return $rank ?: strcmp(implode(':', array_column($a, 'chunk_id')), implode(':', array_column($b, 'chunk_id')));
    }
}
