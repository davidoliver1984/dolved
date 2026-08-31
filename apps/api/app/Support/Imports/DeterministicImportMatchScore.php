<?php

declare(strict_types=1);

namespace App\Support\Imports;

final class DeterministicImportMatchScore
{
    public function basisPoints(string $left, string $right): int
    {
        $leftCharacters = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightCharacters = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $maximum = max(count($leftCharacters), count($rightCharacters));
        if ($maximum === 0) {
            return 0;
        }
        $distance = $this->distance($leftCharacters, $rightCharacters);

        return intdiv(max(0, $maximum - $distance) * 10_000, $maximum);
    }

    /** @param list<string> $left @param list<string> $right */
    private function distance(array $left, array $right): int
    {
        $previous = range(0, count($right));
        foreach ($left as $leftIndex => $leftCharacter) {
            $current = [$leftIndex + 1];
            foreach ($right as $rightIndex => $rightCharacter) {
                $current[] = min(
                    $current[$rightIndex] + 1,
                    $previous[$rightIndex + 1] + 1,
                    $previous[$rightIndex] + ($leftCharacter === $rightCharacter ? 0 : 1),
                );
            }
            $previous = $current;
        }

        return $previous[count($right)];
    }
}
