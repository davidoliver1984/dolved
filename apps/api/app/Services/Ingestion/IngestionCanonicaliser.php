<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

class IngestionCanonicaliser
{
    /** @param array<string, mixed> $chunk */
    public function chunkContentDigest(array $chunk): string
    {
        $fields = [];
        foreach ([
            'chunk_id', 'ordinal', 'text', 'token_count', 'strategy_name',
            'strategy_version', 'configuration', 'configuration_fingerprint',
            'provenance',
        ] as $name) {
            $fields[$name] = $chunk[$name];
        }

        return hash('sha256', $this->canonicalJson($fields));
    }

    /** @param array<int, array{chunk_id: string, ordinal: int, content_digest: string}> $chunks */
    public function chunkManifestDigest(array $chunks): string
    {
        usort($chunks, static fn (array $left, array $right): int => $left['ordinal'] <=> $right['ordinal']);

        return hash('sha256', $this->canonicalJson($chunks));
    }

    /** @param array<int, string> $pointIds */
    public function pointManifestDigest(array $pointIds): string
    {
        sort($pointIds, SORT_STRING);

        return hash('sha256', implode("\n", $pointIds));
    }

    /** @param array<mixed> $value */
    private function canonicalJson(array $value): string
    {
        $normalised = $this->sortRecursively($value);

        return json_encode(
            $normalised,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /** @param array<mixed> $value @return array<mixed> */
    private function sortRecursively(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? $this->sortRecursively($item) : $item,
                $value,
            );
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
