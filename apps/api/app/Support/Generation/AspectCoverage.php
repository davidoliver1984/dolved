<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Enums\GenerationOutcome;
use InvalidArgumentException;

/** ADR-0038: structural projection, never a second generated answer or semantic judge. */
final class AspectCoverage
{
    /**
     * @param  list<string>  $needIds
     * @param  list<array<string, mixed>>  $links
     * @return array<string, string>
     */
    public static function derive(GenerationResult $result, array $needIds, array $links): array
    {
        if (! array_is_list($needIds) || count($needIds) < 1 || count($needIds) > 3
            || count(array_unique($needIds)) !== count($needIds)
            || array_diff($needIds, ['n1', 'n2', 'n3']) !== []
            || ! array_is_list($links) || count($links) !== count($needIds)) {
            throw new InvalidArgumentException('Invalid aspect need identity.');
        }
        $parts = count($result->answerParts);
        $gaps = count($result->unsupportedAspects);
        $shapeValid = match ($result->outcome) {
            GenerationOutcome::Answered => $parts > 0 && $gaps === 0 && $result->insufficiencyReason === null,
            GenerationOutcome::Qualified => $parts > 0 && $gaps > 0 && $result->insufficiencyReason === null,
            GenerationOutcome::InsufficientEvidence => $parts === 0 && $gaps > 0 && $result->insufficiencyReason !== null,
        };
        if (! $shapeValid) {
            throw new InvalidArgumentException('Invalid generation outcome shape.');
        }
        $statuses = [];
        $seenParts = [];
        $seenGaps = [];
        foreach ($links as $link) {
            if (! is_array($link) || count($link) !== 3
                || array_diff(array_keys($link), ['need_id', 'answer_part_indices', 'unsupported_aspect_indices']) !== []
                || ! is_string($link['need_id'] ?? null)
                || ! in_array($link['need_id'], $needIds, true)
                || isset($statuses[$link['need_id']])) {
                throw new InvalidArgumentException('Aspect links must cover each need exactly once.');
            }
            $answers = self::indices($link['answer_part_indices'] ?? null, $parts);
            $unsupported = self::indices($link['unsupported_aspect_indices'] ?? null, $gaps);
            if ($answers === [] && $unsupported === []) {
                throw new InvalidArgumentException('Every need must map an answer or gap.');
            }
            $seenParts = array_merge($seenParts, $answers);
            $seenGaps = array_merge($seenGaps, $unsupported);
            $statuses[$link['need_id']] = $answers === [] ? 'unsupported' : ($unsupported === [] ? 'supported' : 'partial');
        }
        if (count(array_unique($seenParts)) !== $parts || count(array_unique($seenGaps)) !== $gaps) {
            throw new InvalidArgumentException('Every authoritative answer and gap must be mapped.');
        }
        $values = array_values($statuses);
        $valid = match ($result->outcome) {
            GenerationOutcome::Answered => array_diff($values, ['supported']) === [],
            GenerationOutcome::Qualified => array_intersect($values, ['supported', 'partial']) !== []
                && array_intersect($values, ['partial', 'unsupported']) !== [],
            GenerationOutcome::InsufficientEvidence => array_diff($values, ['unsupported']) === [],
        };
        if (! $valid) {
            throw new InvalidArgumentException('Generation outcome contradicts aspect coverage.');
        }

        // Order the diagnostic projection by the validated request, not model order.
        return array_replace(array_fill_keys($needIds, ''), $statuses);
    }

    /** @return list<int> */
    private static function indices(mixed $indices, int $count): array
    {
        if (! is_array($indices) || ! array_is_list($indices)) {
            throw new InvalidArgumentException('Invalid aspect reference list.');
        }
        $seen = [];
        foreach ($indices as $index) {
            if (! is_int($index) || $index < 0 || $index >= $count || isset($seen[$index])) {
                throw new InvalidArgumentException('Aspect references must be unique and in range.');
            }
            $seen[$index] = true;
        }

        return $indices;
    }
}
