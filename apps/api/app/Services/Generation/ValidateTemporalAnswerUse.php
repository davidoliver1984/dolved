<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Exceptions\GenerationException;
use App\Services\Retrieval\TemporalPeriodResolver;
use App\Support\Generation\GenerationRequest;
use Carbon\CarbonImmutable;

final class ValidateTemporalAnswerUse
{
    public function handle(GenerationRequest $request, array $parts, array $links, array $uses, bool $associationScoped = false): array
    {
        $needs = collect($request->evidenceNeeds)->keyBy('need_id');
        $evidence = collect($request->evidence)->keyBy('evidenceId');
        $expected = [];
        foreach ($links as $link) {
            if (($needs[$link['need_id']]['temporal_targets'] ?? []) !== []) {
                foreach ($link['answer_part_indices'] as $index) {
                    $expected[$associationScoped ? $index.':'.$link['need_id'] : $index] = $link['need_id'];
                }
            }
        }
        $seen = [];
        $validated = [];
        $assigned = [];
        foreach ($uses as $use) {
            $key = is_array($use) && is_int($use['part_index'] ?? null) && is_string($use['need_id'] ?? null)
                ? ($associationScoped ? $use['part_index'].':'.$use['need_id'] : $use['part_index']) : null;
            if (! is_array($use) || count($use) !== ($associationScoped ? 5 : 4) || ! is_int($use['part_index'] ?? null)
                || ! is_string($use['need_id'] ?? null) || ! is_string($use['target_id'] ?? null)
                || ! is_string($use['question_quote'] ?? null) || trim($use['question_quote']) === ''
                || isset($seen[$key]) || ($expected[$key] ?? null) !== $use['need_id']) {
                throw new GenerationException('Temporal answer-use coverage is invalid.');
            }
            $seen[$key] = $use['need_id'];
            $need = $needs[$use['need_id']];
            $handles = $associationScoped ? ($use['evidence_ids'] ?? null) : $parts[$use['part_index']]->evidenceIds;
            if (! is_array($handles) || $handles === [] || ! array_is_list($handles)
                || array_any($handles, fn ($id) => ! is_string($id)) || count(array_unique($handles)) !== count($handles)
                || array_diff($handles, $parts[$use['part_index']]->evidenceIds) !== []
                || ($associationScoped && array_diff($handles, $need['evidence_ids'] ?? []) !== [])) {
                throw new GenerationException('Temporal association evidence ownership is invalid.');
            }
            $assigned[$use['part_index']] = array_unique([...($assigned[$use['part_index']] ?? []), ...$handles]);
            $target = collect($need['temporal_targets'])->firstWhere('target_id', $use['target_id']);
            if ($target === null || $target['status'] !== 'searched' || $target['need_id'] !== $use['need_id']
                || ! array_any([$need['question_span'], ...($need['context_spans'] ?? [])],
                    fn ($span) => str_contains($span['quote'], $use['question_quote']))) {
                throw new GenerationException('Temporal answer target is unavailable or ungrounded.');
            }
            if ($target['mode'] === 'dated' && (! is_array($target['reference'])
                || ! str_contains($use['question_quote'], $target['reference']['quote']))) {
                throw new GenerationException('Temporal answer reference mismatch.');
            }
            foreach ($handles as $handle) {
                $item = $evidence->get($handle)?->forNeed($use['need_id']);
                $authority = $item?->temporalAuthority ?? [];
                $version = collect($target['versions'])->firstWhere('version_id', $authority['document_version_id'] ?? null);
                if (($authority['need_id'] ?? null) !== $use['need_id']
                    || ($authority['document_family_id'] ?? null) !== $target['family_id']
                    || ! in_array($target['target_id'], $authority['temporal_target_ids'] ?? [], true) || $version === null) {
                    throw new GenerationException('Temporal evidence ownership or version mismatch.');
                }
                if ($target['at'] !== null) {
                    $at = CarbonImmutable::parse($target['at']);
                    if ($version['start'] === null || $at->lt(CarbonImmutable::parse($version['start']))
                        || ($version['end'] !== null && $at->gte(CarbonImmutable::parse($version['end'])))) {
                        throw new GenerationException('Temporal evidence interval mismatch.');
                    }
                }
                $reference = $target['reference'];
                if ($reference !== null && ($reference['precision'] ?? 'day') !== 'day') {
                    $window = app(TemporalPeriodResolver::class)->window($reference['value'] ?? '');
                    if ($target['at'] !== null || $window === null
                        || $window[0]->toIso8601String() !== ($reference['period_start'] ?? null)
                        || $window[1]->toIso8601String() !== ($reference['period_end'] ?? null)
                        || $version['start'] === null || $window[0]->lt(CarbonImmutable::parse($version['start']))
                        || ($version['end'] !== null && $window[1]->gt(CarbonImmutable::parse($version['end'])))) {
                        throw new GenerationException('Temporal evidence interval mismatch.');
                    }
                }
            }
            $requested = collect($need['temporal_targets'])->firstWhere('purpose', 'requested');
            $validated[] = [...$use, 'target' => $target,
                'planner_disagreement' => array_column($target['versions'], 'version_id') !== array_column($requested['versions'] ?? [], 'version_id')];
        }
        ksort($expected);
        ksort($seen);
        if ($seen !== $expected) {
            throw new GenerationException('Temporal answer-use coverage is incomplete.');
        }
        if ($associationScoped) {
            foreach ($assigned as $index => $handles) {
                if (array_diff($parts[$index]->evidenceIds, $handles) !== []) {
                    throw new GenerationException('Temporal association evidence coverage is incomplete.');
                }
            }
        }

        return ['version' => $associationScoped ? 'temporal-answer-use-v2' : 'temporal-answer-use-v1', 'uses' => $validated,
            'targets' => $needs->flatMap(fn ($need) => $need['temporal_targets'] ?? [])->values()->all()];
    }
}
