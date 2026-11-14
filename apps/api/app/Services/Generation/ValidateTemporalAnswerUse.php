<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Exceptions\GenerationException;
use App\Support\Generation\GenerationRequest;
use Carbon\CarbonImmutable;

final class ValidateTemporalAnswerUse
{
    public function handle(GenerationRequest $request, array $parts, array $links, array $uses): array
    {
        $needs = collect($request->evidenceNeeds)->keyBy('need_id');
        $evidence = collect($request->evidence)->keyBy('evidenceId');
        $expected = [];
        foreach ($links as $link) {
            if (($needs[$link['need_id']]['temporal_targets'] ?? []) !== []) {
                foreach ($link['answer_part_indices'] as $index) {
                    $expected[$index] = $link['need_id'];
                }
            }
        }
        $seen = [];
        $validated = [];
        foreach ($uses as $use) {
            if (! is_array($use) || count($use) !== 4 || ! is_int($use['part_index'] ?? null)
                || ! is_string($use['need_id'] ?? null) || ! is_string($use['target_id'] ?? null)
                || ! is_string($use['question_quote'] ?? null) || trim($use['question_quote']) === ''
                || isset($seen[$use['part_index']]) || ($expected[$use['part_index']] ?? null) !== $use['need_id']) {
                throw new GenerationException('Temporal answer-use coverage is invalid.');
            }
            $seen[$use['part_index']] = $use['need_id'];
            $need = $needs[$use['need_id']];
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
            foreach ($parts[$use['part_index']]->evidenceIds as $handle) {
                $item = $evidence->get($handle);
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

        return ['version' => 'temporal-answer-use-v1', 'uses' => $validated,
            'targets' => $needs->flatMap(fn ($need) => $need['temporal_targets'] ?? [])->values()->all()];
    }
}
