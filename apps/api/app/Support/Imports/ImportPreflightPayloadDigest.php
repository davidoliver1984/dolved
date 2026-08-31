<?php

declare(strict_types=1);

namespace App\Support\Imports;

final class ImportPreflightPayloadDigest
{
    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        $this->sort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $value */
    private function sort(array &$value): void
    {
        ksort($value, SORT_STRING);
        foreach ($value as &$child) {
            if (is_array($child) && ! array_is_list($child)) {
                $this->sort($child);
            }
        }
    }
}
