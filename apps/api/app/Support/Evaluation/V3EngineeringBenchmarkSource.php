<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use JsonException;
use RuntimeException;

final readonly class V3EngineeringBenchmarkSource
{
    public function __construct(private BenchmarkCanonicalJson $canonical) {}

    /**
     * @return array{corpus: array<string, mixed>, expectations: array<string, mixed>, manifest: array<string, mixed>, independence: array<string, mixed>, organisation: array<string, mixed>, catalog: array<string, mixed>, provisioning: array<string, mixed>}
     */
    public function load(): array
    {
        $source = [
            'corpus' => $this->json('corpus.json'),
            'expectations' => $this->json('expectations.json'),
            'manifest' => $this->json('population-manifest.json'),
            'independence' => $this->json('independence.json'),
            'organisation' => $this->json('organisation.json'),
            'catalog' => $this->json('document-catalog.json'),
            'provisioning' => $this->json('provisioning-definition.json'),
        ];
        $manifest = $source['manifest'];
        $corpus = $source['corpus'];
        $expectations = $source['expectations'];
        $provisioning = $source['provisioning'];
        $cases = $corpus['cases'] ?? null;
        $expectationRows = $expectations['expectations'] ?? null;
        if (
            ($manifest['population_id'] ?? null) !== V3EngineeringBenchmark::POPULATION_ID
            || ($manifest['population_digest'] ?? null) !== V3EngineeringBenchmark::POPULATION_DIGEST
            || ($manifest['benchmark_authoring_digest'] ?? null) !== V3EngineeringBenchmark::BENCHMARK_AUTHORING_DIGEST
            || ($manifest['case_count'] ?? null) !== V3EngineeringBenchmark::EXPECTED_CASES
            || ($manifest['variant_count'] ?? null) !== V3EngineeringBenchmark::EXPECTED_VARIANTS
            || ! is_array($cases)
            || count($cases) !== V3EngineeringBenchmark::EXPECTED_CASES
            || ! is_array($expectationRows)
            || count($expectationRows) !== V3EngineeringBenchmark::EXPECTED_VARIANTS
            || ($manifest['corpus_file_sha256'] ?? null) !== hash_file('sha256', V3EngineeringBenchmark::root().'/corpus.json')
            || ($manifest['expectations_file_sha256'] ?? null) !== hash_file('sha256', V3EngineeringBenchmark::root().'/expectations.json')
            || ($provisioning['definition_digest'] ?? null) !== V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST
            || ($provisioning['benchmark']['population_digest'] ?? null) !== V3EngineeringBenchmark::POPULATION_DIGEST
            || ($provisioning['status'] ?? null) !== 'DEFINITION_ONLY'
            || ($provisioning['provider_calls_performed'] ?? null) !== false
        ) {
            throw new RuntimeException('The Benchmark V3 engineering population or provisioning definition is invalid.');
        }
        $definition = $provisioning;
        unset($definition['definition_digest']);
        if ($this->canonical->digest($definition) !== V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST) {
            throw new RuntimeException('The Benchmark V3 provisioning definition digest does not match its content.');
        }
        $actualPairs = collect($cases)->flatMap(
            fn (mixed $case): array => is_array($case) && is_array($case['variants'] ?? null)
                ? array_map(
                    fn (mixed $variant): array => [
                        'case_id' => $case['case_id'] ?? null,
                        'variant_id' => is_array($variant) ? ($variant['variant_id'] ?? null) : null,
                    ],
                    $case['variants'],
                )
                : [],
        )->values()->all();
        $expectedPairs = collect($expectationRows)->map(
            fn (mixed $row): array => is_array($row) ? [
                'case_id' => $row['case_id'] ?? null,
                'variant_id' => $row['variant_id'] ?? null,
            ] : [],
        )->values()->all();
        if ($actualPairs !== $expectedPairs || count(array_unique(array_map('serialize', $actualPairs))) !== count($actualPairs)) {
            throw new RuntimeException('The Benchmark V3 engineering expectation identities are inconsistent.');
        }

        return $source;
    }

    /** @return array<string, mixed> */
    private function json(string $name): array
    {
        $path = V3EngineeringBenchmark::root().'/'.$name;
        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid Benchmark V3 engineering JSON: {$path}", 0, $exception);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException("Benchmark V3 engineering JSON is not an object: {$path}");
        }

        return $decoded;
    }
}
