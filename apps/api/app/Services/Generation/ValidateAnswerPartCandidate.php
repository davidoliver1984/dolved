<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Exceptions\GenerationStreamException;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationRequest;

final class ValidateAnswerPartCandidate
{
    /** @param array<string, mixed> $payload */
    public function handle(GenerationRequest $request, array $payload): AnswerPartResult
    {
        $keys = array_keys($payload);
        sort($keys, SORT_STRING);
        if ($keys !== ['evidence_ids', 'text'] || ! is_string($payload['text'] ?? null)
            || trim($payload['text']) === '' || ! is_array($payload['evidence_ids'] ?? null)
            || $payload['evidence_ids'] === []) {
            throw new GenerationStreamException('An answer part candidate is structurally invalid.');
        }
        $ids = array_values($payload['evidence_ids']);
        $authorised = collect($request->evidence)->pluck('evidenceId');
        if (count(array_unique($ids)) !== count($ids)
            || collect($ids)->contains(fn (mixed $id): bool => ! is_string($id) || ! $authorised->contains($id))) {
            throw new GenerationStreamException('An answer part candidate cites unauthorised evidence.');
        }

        return new AnswerPartResult($payload['text'], $ids);
    }
}
