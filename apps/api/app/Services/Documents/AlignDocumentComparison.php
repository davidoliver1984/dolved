<?php

declare(strict_types=1);

namespace App\Services\Documents;

final class AlignDocumentComparison
{
    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return array<string, mixed>
     */
    public function handle(array $before, array $after, bool $contentAvailable, bool $partial): array
    {
        if (! $contentAvailable) {
            return $this->result('unavailable', 'Extracted content is unavailable for one or both versions, so alignment cannot be trusted.', []);
        }

        $left = $this->withSections($before);
        $right = $this->withSections($after);
        $anchors = $this->longestCommonSubsequence($left, $right);
        $leftMatched = [];
        $rightMatched = [];
        $differences = [];

        foreach ($anchors as [$leftIndex, $rightIndex]) {
            $leftMatched[$leftIndex] = true;
            $rightMatched[$rightIndex] = true;
            $differences[] = $this->difference('unchanged', $left[$leftIndex], $right[$rightIndex]);
        }

        // Exact blocks outside the order-preserving sequence are genuine moves.
        foreach ($left as $leftIndex => $element) {
            if (isset($leftMatched[$leftIndex])) {
                continue;
            }
            foreach ($right as $rightIndex => $candidate) {
                if (! isset($rightMatched[$rightIndex]) && $this->fingerprint($element) === $this->fingerprint($candidate)) {
                    $leftMatched[$leftIndex] = true;
                    $rightMatched[$rightIndex] = true;
                    $differences[] = $this->difference('moved', $element, $candidate);
                    break;
                }
            }
        }

        // Modified elements pair only within the same extracted section and kind.
        foreach ($left as $leftIndex => $element) {
            if (isset($leftMatched[$leftIndex])) {
                continue;
            }
            foreach ($right as $rightIndex => $candidate) {
                if (! isset($rightMatched[$rightIndex])
                    && $element['_section'] === $candidate['_section']
                    && $element['kind'] === $candidate['kind']) {
                    $leftMatched[$leftIndex] = true;
                    $rightMatched[$rightIndex] = true;
                    $differences[] = $this->difference('modified', $element, $candidate);
                    break;
                }
            }
        }

        foreach ($left as $index => $element) {
            if (! isset($leftMatched[$index])) {
                $differences[] = $this->difference('removed', $element, null);
            }
        }
        foreach ($right as $index => $element) {
            if (! isset($rightMatched[$index])) {
                $differences[] = $this->difference('added', null, $element);
            }
        }

        usort($differences, static fn (array $a, array $b): int => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);

        return $this->result(
            $partial ? 'partial' : 'reliable',
            $partial ? 'Alignment is partial because extraction warnings or the 500-element bound affect one or both versions.' : null,
            $differences,
        );
    }

    /** @param list<array<string, mixed>> $elements @return list<array<string, mixed>> */
    private function withSections(array $elements): array
    {
        $section = 'Document start';

        return array_map(function (array $element) use (&$section): array {
            $element['_section'] = $section;
            if ($element['kind'] === 'heading' && trim((string) $element['text']) !== '') {
                $section = trim((string) $element['text']);
            }

            return $element;
        }, $elements);
    }

    /**
     * @param  list<array<string, mixed>>  $left
     * @param  list<array<string, mixed>>  $right
     * @return list<array{int, int}>
     */
    private function longestCommonSubsequence(array $left, array $right): array
    {
        $rows = count($left);
        $columns = count($right);
        $matrix = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $matrix[$i][$j] = $this->fingerprint($left[$i]) === $this->fingerprint($right[$j])
                    ? 1 + $matrix[$i + 1][$j + 1]
                    : max($matrix[$i + 1][$j], $matrix[$i][$j + 1]);
            }
        }

        $pairs = [];
        for ($i = 0, $j = 0; $i < $rows && $j < $columns;) {
            if ($this->fingerprint($left[$i]) === $this->fingerprint($right[$j])) {
                $pairs[] = [$i++, $j++];
            } elseif ($matrix[$i + 1][$j] >= $matrix[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $pairs;
    }

    /** @param array<string, mixed> $element */
    private function fingerprint(array $element): string
    {
        return hash('sha256', $element['kind']."\0".preg_replace('/\s+/u', ' ', trim((string) $element['text'])));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @return array<string, mixed>
     */
    private function difference(string $status, ?array $before, ?array $after): array
    {
        $section = (string) ($after['_section'] ?? $before['_section'] ?? 'Document start');
        if ($before !== null) {
            unset($before['_section']);
        }
        if ($after !== null) {
            unset($after['_section']);
        }
        $position = (int) ($after['ordinal'] ?? $before['ordinal'] ?? 0);

        return [
            'id' => hash('sha256', implode('|', [$status, $before['id'] ?? '', $after['id'] ?? ''])),
            'position' => $position,
            'section' => $section,
            'status' => $status,
            'before' => $before,
            'after' => $after,
        ];
    }

    /** @param list<array<string, mixed>> $differences @return array<string, mixed> */
    private function result(string $status, ?string $reason, array $differences): array
    {
        $counts = array_fill_keys(['added', 'removed', 'modified', 'moved', 'unchanged'], 0);
        foreach ($differences as $difference) {
            $counts[$difference['status']]++;
        }

        return [
            'alignment_status' => $status,
            'alignment_reason' => $reason,
            'formatting_comparison' => 'unavailable',
            'formatting_reason' => 'The extraction projection does not retain inline formatting signals.',
            'change_counts' => $counts,
            'differences' => $differences,
        ];
    }
}
