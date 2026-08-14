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
    public function engineeringCorpus(): array
    {
        $snapshot = $this->absoluteJson(EngineeringBenchmark::ENGINEERING_SNAPSHOT);
        $recordedDigest = $snapshot['snapshot_digest'] ?? null;
        unset($snapshot['snapshot_digest']);
        $caseIds = $snapshot['split']['case_ids'] ?? null;
        $cases = $snapshot['cases'] ?? null;
        if (
            ! is_string($recordedDigest)
            || ! hash_equals($recordedDigest, $this->canonical->digest($snapshot))
            || ($snapshot['schema_version'] ?? null) !== 'v1'
            || ($snapshot['benchmark']['id'] ?? null) !== EngineeringBenchmark::ID
            || ($snapshot['benchmark']['version'] ?? null) !== EngineeringBenchmark::VERSION
            || ($snapshot['benchmark']['digest'] ?? null) !== EngineeringBenchmark::DIGEST
            || ($snapshot['split']['name'] ?? null) !== 'engineering_tuning'
            || ! is_array($caseIds)
            || count($caseIds) !== EngineeringBenchmark::EXPECTED_ENGINEERING_CASES
            || ($snapshot['split']['case_ids_digest'] ?? null) !== $this->canonical->digest($caseIds)
            || ($snapshot['split']['case_ids_digest'] ?? null) !== EngineeringBenchmark::ENGINEERING_CASE_IDS_DIGEST
            || ! is_array($cases)
            || count($cases) !== EngineeringBenchmark::EXPECTED_ENGINEERING_CASES
            || ($snapshot['case_count'] ?? null) !== EngineeringBenchmark::EXPECTED_ENGINEERING_CASES
            || ($snapshot['variant_count'] ?? null) !== EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS
            || ($snapshot['source']['experiment_id'] ?? null) !== 'EXP-0001-alderbridge-initial-hybrid'
            || ($snapshot['source']['application_observations_sha256'] ?? null) !== EngineeringBenchmark::ENGINEERING_SNAPSHOT_SOURCE_DIGEST
        ) {
            throw new RuntimeException('The engineering-only benchmark snapshot is invalid.');
        }
        $actualIds = collect($cases)->map(
            fn (mixed $case): mixed => is_array($case) ? ($case['case_id'] ?? null) : null,
        )->all();
        $variantCount = collect($cases)->sum(
            fn (mixed $case): int => is_array($case) && is_array($case['variants'] ?? null)
                ? count($case['variants'])
                : 0,
        );
        if ($actualIds !== $caseIds || count(array_unique($actualIds)) !== count($actualIds)
            || $variantCount !== EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS) {
            throw new RuntimeException('The engineering-only benchmark cases are inconsistent.');
        }
        $snapshot['snapshot_digest'] = $recordedDigest;

        return $snapshot;
    }

    /** @return array<string, mixed> */
    public function calibrationCorpus(): array
    {
        $snapshot = $this->absoluteJson(EngineeringBenchmark::CALIBRATION_SNAPSHOT);
        $recordedDigest = $snapshot['snapshot_digest'] ?? null;
        unset($snapshot['snapshot_digest']);
        $caseIds = $snapshot['split']['case_ids'] ?? null;
        $cases = $snapshot['cases'] ?? null;
        if (
            ! is_string($recordedDigest)
            || ! hash_equals($recordedDigest, $this->canonical->digest($snapshot))
            || ($snapshot['schema_version'] ?? null) !== 'v1'
            || ($snapshot['benchmark']['id'] ?? null) !== EngineeringBenchmark::ID
            || ($snapshot['benchmark']['version'] ?? null) !== EngineeringBenchmark::VERSION
            || ($snapshot['benchmark']['digest'] ?? null) !== EngineeringBenchmark::DIGEST
            || ($snapshot['split']['name'] ?? null) !== ThresholdCalibrationDefinition::SPLIT
            || ! is_array($caseIds)
            || count($caseIds) !== ThresholdCalibrationDefinition::EXPECTED_CASES
            || ($snapshot['split']['case_ids_digest'] ?? null) !== $this->canonical->digest($caseIds)
            || ! is_array($cases)
            || count($cases) !== ThresholdCalibrationDefinition::EXPECTED_CASES
            || ($snapshot['case_count'] ?? null) !== ThresholdCalibrationDefinition::EXPECTED_CASES
            || ($snapshot['variant_count'] ?? null) !== ThresholdCalibrationDefinition::EXPECTED_VARIANTS
        ) {
            throw new RuntimeException('The calibration-only benchmark snapshot is invalid.');
        }
        $actualIds = collect($cases)->map(
            fn (mixed $case): mixed => is_array($case) ? ($case['case_id'] ?? null) : null,
        )->all();
        $variantCount = collect($cases)->sum(
            fn (mixed $case): int => is_array($case) && is_array($case['variants'] ?? null)
                ? count($case['variants'])
                : 0,
        );
        if ($actualIds !== $caseIds || count(array_unique($actualIds)) !== count($actualIds)
            || $variantCount !== ThresholdCalibrationDefinition::EXPECTED_VARIANTS) {
            throw new RuntimeException('The calibration-only benchmark cases are inconsistent.');
        }
        $snapshot['snapshot_digest'] = $recordedDigest;

        return $snapshot;
    }

    /** @return array<string, mixed> */
    public function calExp0002Corpus(): array
    {
        return $this->versionedCalibrationPopulation(
            CalExp0002Definition::class,
            'CAL-EXP-0002',
        );
    }

    /** @return array<string, mixed> */
    public function calExp0003Corpus(): array
    {
        return $this->versionedCalibrationPopulation(
            CalExp0003Definition::class,
            'CAL-EXP-0003',
        );
    }

    /**
     * @param  class-string<CalExp0002Definition|CalExp0003Definition>  $definition
     * @return array<string, mixed>
     */
    private function versionedCalibrationPopulation(string $definition, string $label): array
    {
        $snapshot = $this->absoluteJson(EngineeringBenchmark::CALIBRATION_SNAPSHOT);
        $manifest = $this->absoluteJson(EngineeringBenchmark::CAL_EXP_0002_POPULATION_MANIFEST);
        $compatibility = $this->absoluteJson(EngineeringBenchmark::CAL_EXP_0002_COMPATIBILITY_RESULT);
        $cases = $snapshot['cases'] ?? null;
        if (! is_array($cases)) {
            throw new RuntimeException("The {$label} population cases are unavailable.");
        }
        $caseIds = collect($cases)->map(
            fn (mixed $case): mixed => is_array($case) ? ($case['case_id'] ?? null) : null,
        )->all();
        $variantCount = collect($cases)->sum(
            fn (mixed $case): int => is_array($case) && is_array($case['variants'] ?? null)
                ? count($case['variants'])
                : 0,
        );
        if (
            ($snapshot['schema_version'] ?? null) !== 'v3'
            || ($snapshot['benchmark']['id'] ?? null) !== EngineeringBenchmark::ID
            || ($snapshot['benchmark']['version'] ?? null) !== $definition::BENCHMARK_VERSION
            || ($snapshot['benchmark']['digest'] ?? null) !== $definition::BENCHMARK_DIGEST
            || ($snapshot['split']['name'] ?? null) !== $definition::SPLIT
            || ($snapshot['split']['version'] ?? null) !== $definition::SPLIT_VERSION
            || ($snapshot['split']['case_ids_digest'] ?? null) !== $definition::CASE_IDS_DIGEST
            || count($cases) !== $definition::EXPECTED_CASES
            || $variantCount !== $definition::EXPECTED_VARIANTS
            || count(array_unique($caseIds)) !== count($caseIds)
            || $this->canonical->digest($caseIds) !== $definition::CASE_IDS_DIGEST
            || ($manifest['population_digest'] ?? null) !== $definition::POPULATION_DIGEST
            || ($manifest['case_ids_digest'] ?? null) !== $definition::CASE_IDS_DIGEST
            || ($manifest['case_count'] ?? null) !== $definition::EXPECTED_CASES
            || ($manifest['variant_count'] ?? null) !== $definition::EXPECTED_VARIANTS
            || ($compatibility['compatible'] ?? null) !== true
            || ($compatibility['result_digest'] ?? null) !== $definition::COMPATIBILITY_RESULT_DIGEST
            || ($compatibility['compatibility_policy_sha256'] ?? null) !== $definition::COMPATIBILITY_POLICY_DIGEST
        ) {
            throw new RuntimeException("The isolated {$label} population or compatibility lineage is invalid.");
        }
        $snapshot['split']['case_ids'] = $caseIds;
        $snapshot['case_count'] = count($cases);
        $snapshot['variant_count'] = $variantCount;
        $snapshot['snapshot_digest'] = hash_file('sha256', EngineeringBenchmark::CALIBRATION_SNAPSHOT);

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function json(string $relativePath): array
    {
        return $this->absoluteJson(EngineeringBenchmark::ROOT.'/'.$relativePath);
    }

    /** @return array<string, mixed> */
    private function absoluteJson(string $path): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid benchmark JSON: {$path}", 0, $exception);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException("Benchmark JSON is not an object: {$path}");
        }

        return $decoded;
    }
}
