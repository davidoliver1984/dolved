<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Enums\GenerationSide;
use App\Exceptions\GenerationContextBudgetExceeded;
use App\Support\Generation\GenerationEvidence;

final class GenerationContextPacker
{
    /**
     * @param  list<GenerationEvidence>  $orderedEvidence
     * @param  list<GenerationSide>  $requiredSides
     * @return list<GenerationEvidence>
     */
    public function pack(array $orderedEvidence, array $requiredSides, int $maximumCharacters, string $policyVersion): array
    {
        $mandatory = [];
        foreach ($requiredSides as $requiredSide) {
            $item = collect($orderedEvidence)->first(fn (GenerationEvidence $evidence): bool => $evidence->side === $requiredSide);
            if (! $item instanceof GenerationEvidence) {
                throw new GenerationContextBudgetExceeded($policyVersion, 0, $maximumCharacters);
            }
            $mandatory[$item->evidenceId] = $item;
        }

        $mandatoryUnits = array_sum(array_map(fn (GenerationEvidence $item): int => mb_strlen($item->text, 'UTF-8'), $mandatory));
        if ($mandatoryUnits > $maximumCharacters) {
            throw new GenerationContextBudgetExceeded($policyVersion, $mandatoryUnits, $maximumCharacters);
        }

        $selectedIds = array_fill_keys(array_keys($mandatory), true);
        $used = $mandatoryUnits;
        foreach ($orderedEvidence as $item) {
            if (isset($selectedIds[$item->evidenceId])) {
                continue;
            }
            $units = mb_strlen($item->text, 'UTF-8');
            if ($used + $units <= $maximumCharacters) {
                $selectedIds[$item->evidenceId] = true;
                $used += $units;
            }
        }

        return array_values(array_filter(
            $orderedEvidence,
            fn (GenerationEvidence $item): bool => isset($selectedIds[$item->evidenceId]),
        ));
    }
}
