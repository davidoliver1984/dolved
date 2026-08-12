<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use JsonException;
use RuntimeException;

final readonly class EngineeringBenchmarkSource
{
    public function __construct(private BenchmarkCanonicalJson $canonical) {}

    /** @return array{manifest: array<string, mixed>, organisation: array<string, mixed>, catalog: array<string, mixed>, split: array<string, mixed>, checksums: array<string, mixed>} */
    public function load(): array
    {
        $source = [
            'manifest' => $this->json('manifest.json'),
            'organisation' => $this->json('organisation.json'),
            'catalog' => $this->json('document-catalog.json'),
            'split' => $this->json('splits/v1.json'),
            'checksums' => $this->json('compiled/checksums.json'),
        ];

        $manifest = $source['manifest'];
        $checksums = $source['checksums'];
        if (
            ($manifest['benchmark_id'] ?? null) !== EngineeringBenchmark::ID
            || ($manifest['corpus_version'] ?? null) !== '2'
            || ($manifest['status'] ?? null) !== 'COMPLETE'
            || ($manifest['authored_counts']['document_families'] ?? null) !== EngineeringBenchmark::EXPECTED_FAMILIES
            || ($manifest['authored_counts']['document_versions'] ?? null) !== EngineeringBenchmark::EXPECTED_VERSIONS
            || ! is_string($checksums['benchmark_digest'] ?? null)
        ) {
            throw new RuntimeException('The committed engineering benchmark is incomplete or has unexpected identity.');
        }

        foreach (($checksums['files'] ?? []) as $relative => $expected) {
            if (! is_string($relative) || ! is_string($expected)) {
                throw new RuntimeException('The engineering benchmark checksum manifest is malformed.');
            }
            $path = EngineeringBenchmark::ROOT.'/'.$relative;
            if (! is_file($path) || hash_file('sha256', $path) !== $expected) {
                throw new RuntimeException("Engineering benchmark checksum mismatch: {$relative}");
            }
        }

        return $source;
    }

    public function document(string $relativePath): string
    {
        $root = realpath(EngineeringBenchmark::ROOT);
        $path = realpath(EngineeringBenchmark::ROOT.'/'.$relativePath);
        if ($root === false || $path === false || ! str_starts_with($path, $root.'/')) {
            throw new RuntimeException('A benchmark document path escaped the canonical source root.');
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw new RuntimeException("Benchmark source is empty or unreadable: {$relativePath}");
        }

        return $contents;
    }

    /** @return array<string, mixed> */
    public function compiledCorpus(): array
    {
        return $this->json('compiled/corpus.json');
    }

    /** @return array<string, mixed> */
    private function json(string $relativePath): array
    {
        $path = EngineeringBenchmark::ROOT.'/'.$relativePath;
        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid benchmark JSON: {$relativePath}", 0, $exception);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException("Benchmark JSON is not an object: {$relativePath}");
        }

        return $decoded;
    }
}
