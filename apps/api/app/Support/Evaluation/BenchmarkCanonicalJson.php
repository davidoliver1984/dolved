<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class BenchmarkCanonicalJson
{
    /** @param array<string, mixed> $value */
    public function encode(array $value, bool $pretty = false): string
    {
        $this->sort($value);

        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | ($pretty ? JSON_PRETTY_PRINT : 0),
        ).($pretty ? "\n" : '');
    }

    /** @param array<string, mixed> $value */
    public function digest(array $value): string
    {
        return hash('sha256', $this->encode($value));
    }

    /** @param array<mixed> $value */
    private function sort(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sort($item);
            }
        }
        unset($item);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
    }
}
