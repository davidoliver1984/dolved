<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Exceptions\CoverageSelectionFailure;

/** Deterministic allocation of a validated proposal, not an answerability judge. */
final class CoverageAllocation
{
    public function allocate(array $needs, array $candidates, mixed $proposal): array
    {
        $byId = array_column($candidates, null, 'candidate_id');
        $byNeed = array_column($needs, null, 'need_id');
        $aspects = is_array($proposal) ? ($proposal['aspects'] ?? null) : null;
        if (count($byId) !== count($candidates) || count($candidates) > 20 || ! is_array($aspects)
            || count($aspects) < 1 || count($aspects) > 6) {
            throw new CoverageSelectionFailure('coverage_proposal_bounds', false);
        }
        $seen = [];
        foreach ($aspects as $aspect) {
            if (! is_array($aspect) || ! is_string($aspect['need_id'] ?? null)) {
                throw new CoverageSelectionFailure('coverage_aspect_provenance', false);
            }
            $need = $byNeed[$aspect['need_id'] ?? ''] ?? null;
            if ($need === null || ! is_string($aspect['quote'] ?? null) || trim($aspect['quote']) === ''
                || ! str_contains($need['question_span'], $aspect['quote'])
                || ! in_array($aspect['unmet'] ?? null, ['none', 'no_support', 'uncertain', 'capacity'], true)
                || ! is_array($aspect['support'] ?? null) || count($aspect['support']) > 5
                || (($aspect['unmet'] === 'none') !== ($aspect['support'] !== []))) {
                throw new CoverageSelectionFailure('coverage_aspect_provenance', false);
            }
            $seen[$aspect['need_id']] = true;
            $sides = [];
            foreach ($aspect['support'] as $support) {
                if (! is_array($support) || ! is_string($support['candidate_id'] ?? null)
                    || ! is_string($support['association'] ?? null)) {
                    throw new CoverageSelectionFailure('coverage_support_provenance', false);
                }
                $candidate = $byId[$support['candidate_id'] ?? ''] ?? null;
                if ($candidate === null || ! in_array($support['association'] ?? null, $candidate['associations'], true)
                    || ! str_starts_with($support['association'], $aspect['need_id'].':')
                    || ! is_string($support['quote'] ?? null) || mb_strlen($support['quote']) > 500
                    || ! preg_match('/[\p{L}\p{N}]/u', $support['quote']) || ! str_contains($candidate['text'], $support['quote'])) {
                    throw new CoverageSelectionFailure('coverage_support_provenance', false);
                }
                $sides[] = explode(':', $support['association'])[1];
            }
            if ($aspect['support'] !== [] && $need['operation'] === 'compare'
                && (array_diff(['primary', 'comparison'], $sides) !== [] || array_diff($sides, ['primary', 'comparison']) !== [])) {
                throw new CoverageSelectionFailure('coverage_comparison_side_missing', false);
            }
        }
        if (array_diff(array_keys($byNeed), array_keys($seen)) !== []) {
            throw new CoverageSelectionFailure('coverage_need_omitted', false);
        }
        // At most 20 choose 5 subsets. Maximise proposed complete-aspect units;
        // a multi-passage proposal is a unit, not independently sufficient quotes.
        $ids = array_keys($byId);
        usort($ids, fn ($a, $b) => ($byId[$a]['rank'] <=> $byId[$b]['rank']) ?: strcmp($a, $b));
        $best = [];
        $score = -1;
        $visit = function (int $offset, array $selected) use (&$visit, &$best, &$score, $ids, $aspects): void {
            $covered = count(array_filter($aspects, fn ($a) => $a['support'] !== []
                && array_diff(array_column($a['support'], 'candidate_id'), $selected) === []));
            if ($covered > $score || ($covered === $score && count($selected) > count($best))) {
                $score = $covered;
                $best = $selected;
            }
            if (count($selected) === 5) {
                return;
            }
            for ($i = $offset; $i < count($ids); $i++) {
                $visit($i + 1, [...$selected, $ids[$i]]);
            }
        };
        $visit(0, []);
        $associations = [];
        foreach ($aspects as &$aspect) {
            $aspect['allocated'] = $aspect['support'] !== [] && array_diff(array_column($aspect['support'], 'candidate_id'), $best) === [];
            if (! $aspect['allocated'] && $aspect['unmet'] === 'none') {
                $aspect['unmet'] = 'capacity';
            }
            if ($aspect['allocated']) {
                foreach ($aspect['support'] as $support) {
                    $associations[$support['candidate_id']][$support['association']] = true;
                }
            }
        }
        unset($aspect);

        // Do not attach unused comparison fragments or unrelated candidate owners.
        return ['selected' => array_map(fn ($id) => ['candidate_id' => $id,
            'associations' => array_keys($associations[$id])], array_keys($associations)),
            'aspects' => $aspects, 'semantic_completeness_verified' => false];
    }

    /** Unmet model support never suppresses otherwise authorised ordinary evidence. */
    public function fillFromExisting(array $selected, array $existing, array $needs, array $qualified): array
    {
        $byId = array_column($qualified, null, 'candidate_id');
        $byNeed = array_column($needs, null, 'need_id');
        $result = array_column($selected, null, 'candidate_id');
        $units = [];
        foreach ($existing as $candidate) {
            $need = $byNeed[$candidate['need_id']];
            $key = $need['operation'] === 'compare' ? $need['need_id'].':'.$candidate['document_family_id']
                : $need['need_id'].':'.$candidate['chunk_id'];
            $units[$key][] = $candidate;
        }
        foreach ($units as $unit) {
            $updated = $result;
            foreach ($unit as $candidate) {
                $id = $candidate['chunk_id'];
                $association = $candidate['need_id'].':'.$candidate['side'];
                if (! isset($byId[$id]) || ! in_array($association, $byId[$id]['associations'], true)) {
                    throw new CoverageSelectionFailure('coverage_receiving_authority_lost', true);
                }
                $updated[$id] ??= ['candidate_id' => $id, 'associations' => []];
                $updated[$id]['associations'] = array_values(array_unique([...$updated[$id]['associations'], $association]));
            }
            if (count($updated) <= 5) {
                $result = $updated;
            }
        }

        return array_values($result);
    }
}
