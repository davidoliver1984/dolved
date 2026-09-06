<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Enums\GenerationOutcome;
use App\Exceptions\GenerationException;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\AspectCoverage;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\GenerationResult;

final class ValidateGenerationResult
{
    /** @param list<string> $needIds @param array<string, mixed> $payload */
    public function handleNeeds(GenerationRequest $request, array $needIds, array $payload): GenerationResult
    {
        if ($request->evidenceNeeds !== [] && array_column($request->evidenceNeeds, 'need_id') !== $needIds) {
            throw new GenerationException('Generation result needs differ from the assembled request.');
        }
        if (! $this->hasExactKeys($payload, ['contract_version', 'outcome', 'answer_parts', 'unsupported_aspects', 'insufficiency_reason', 'usage', 'aspect_links'])
            || ($payload['contract_version'] ?? null) !== 2 || ! is_array($payload['aspect_links'])) {
            throw new GenerationException('The needs generation result schema is invalid.');
        }
        $links = $payload['aspect_links'];
        unset($payload['aspect_links']);
        $payload['contract_version'] = 1;
        // Preserve the same citation membership and outcome invariants as v1.
        $result = $this->handle($request, $payload);
        try {
            $statuses = AspectCoverage::derive($result, $needIds, $links);
            if ($request->evidenceNeeds !== []) {
                $owners = array_column($request->evidenceNeeds, 'evidence_ids', 'need_id');
                foreach ($links as $link) {
                    $owned = $owners[$link['need_id']];
                    foreach ($link['answer_part_indices'] as $index) {
                        if (array_diff($result->answerParts[$index]->evidenceIds, $owned) !== []) {
                            throw new \InvalidArgumentException('Answer cites another need evidence.');
                        }
                    }
                    if ($owned === [] && ($link['answer_part_indices'] !== [] || $link['unsupported_aspect_indices'] === [])) {
                        throw new \InvalidArgumentException('Need without evidence requires a gap.');
                    }
                }
            }
        } catch (\InvalidArgumentException $exception) {
            throw new GenerationException('The needs generation coverage is invalid.', previous: $exception);
        }

        return new GenerationResult($result->outcome, $result->answerParts, $result->unsupportedAspects,
            $result->insufficiencyReason, $result->usage, $links, $statuses);
    }

    /** @param array<string, mixed> $payload */
    public function handle(GenerationRequest $request, array $payload): GenerationResult
    {
        if (! $this->hasExactKeys($payload, ['contract_version', 'outcome', 'answer_parts', 'unsupported_aspects', 'insufficiency_reason', 'usage'])
            || ($payload['contract_version'] ?? null) !== 1) {
            throw new GenerationException('The generation result schema is invalid.');
        }
        $outcome = is_string($payload['outcome'] ?? null) ? GenerationOutcome::tryFrom($payload['outcome']) : null;
        $partsPayload = $payload['answer_parts'] ?? null;
        $unsupported = $payload['unsupported_aspects'] ?? null;
        $reason = $payload['insufficiency_reason'] ?? null;
        if ($outcome === null || ! is_array($partsPayload) || ! is_array($unsupported)
            || collect($unsupported)->contains(fn (mixed $item): bool => ! is_string($item) || trim($item) === '')
            || (! is_null($reason) && (! is_string($reason) || trim($reason) === '' || mb_strlen($reason, 'UTF-8') > 8000))) {
            throw new GenerationException('The generation result fields are invalid.');
        }
        $available = collect($request->evidence)->keyBy('evidenceId');
        $parts = [];
        foreach ($partsPayload as $part) {
            if (! is_array($part) || ! $this->hasExactKeys($part, ['text', 'evidence_ids'])
                || ! is_string($part['text']) || trim($part['text']) === '' || ! is_array($part['evidence_ids']) || $part['evidence_ids'] === []) {
                throw new GenerationException('An answer part is structurally invalid.');
            }
            $ids = array_values($part['evidence_ids']);
            if (count(array_unique($ids)) !== count($ids)
                || collect($ids)->contains(fn (mixed $id): bool => ! is_string($id) || ! $available->has($id))) {
                throw new GenerationException('An answer part cites unauthorised evidence.');
            }
            $parts[] = new AnswerPartResult($part['text'], $ids);
        }
        $valid = match ($outcome) {
            GenerationOutcome::Answered => $parts !== [] && $unsupported === [] && $reason === null,
            GenerationOutcome::Qualified => $parts !== [] && $unsupported !== [] && $reason === null,
            GenerationOutcome::InsufficientEvidence => $parts === [] && $unsupported !== [] && is_string($reason),
        };
        if (! $valid) {
            throw new GenerationException('The generation outcome invariant is invalid.');
        }
        $usage = $payload['usage'] ?? null;
        if (! is_null($usage) && ! is_array($usage)) {
            throw new GenerationException('Generation usage must be an object or null.');
        }

        return new GenerationResult($outcome, $parts, array_values($unsupported), $reason, $usage);
    }

    /** @param array<string, mixed> $value @param list<string> $expected */
    private function hasExactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);

        return $keys === $expected;
    }
}
