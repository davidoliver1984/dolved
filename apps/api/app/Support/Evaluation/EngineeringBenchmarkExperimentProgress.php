<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use Carbon\CarbonImmutable;
use JsonException;
use RuntimeException;

final readonly class EngineeringBenchmarkExperimentProgress
{
    public function __construct(private BenchmarkCanonicalJson $canonical) {}

    /** @param array<string, mixed> $lineage @return array<string, mixed> */
    public function initialise(string $runId, array $lineage): array
    {
        $directory = $this->directory($runId);
        $manifestPath = $directory.'/run-manifest.json';
        $digest = $this->canonical->digest($lineage);
        if (is_file($manifestPath)) {
            $manifest = $this->readJson($manifestPath, 'experiment run manifest');
            if (
                ($manifest['run_id'] ?? null) !== $runId
                || ! is_string($manifest['lineage_digest'] ?? null)
                || ! hash_equals($digest, $manifest['lineage_digest'])
                || ! is_array($manifest['lineage'] ?? null)
                || ! hash_equals($digest, $this->canonical->digest($manifest['lineage']))
            ) {
                throw new RuntimeException("{$runId} cannot resume because its immutable lineage differs.");
            }

            return $manifest;
        }
        if (! is_dir($directory.'/observations') && ! mkdir($directory.'/observations', 0o755, true) && ! is_dir($directory.'/observations')) {
            throw new RuntimeException('The experiment progress directory could not be created.');
        }
        $manifest = [
            'schema_version' => 'v1',
            'run_id' => $runId,
            'started_at' => CarbonImmutable::now()->toIso8601String(),
            'lineage_digest' => $digest,
            'lineage' => $lineage,
        ];
        $this->writeAtomic($manifestPath, $manifest);

        return $manifest;
    }

    /** @return array<string, array<string, mixed>> */
    public function observations(string $runId, string $lineageDigest): array
    {
        $result = [];
        foreach (glob($this->directory($runId).'/observations/*.json') ?: [] as $path) {
            $record = $this->readJson($path, 'experiment variant observation');
            $recordedDigest = $record['record_digest'] ?? null;
            unset($record['record_digest']);
            if (
                ! is_string($recordedDigest)
                || ! hash_equals($recordedDigest, $this->canonical->digest($record))
                || ($record['lineage_digest'] ?? null) !== $lineageDigest
                || ! is_string($record['case_id'] ?? null)
                || ! is_string($record['variant_id'] ?? null)
                || ! is_array($record['observation'] ?? null)
            ) {
                throw new RuntimeException('A durable experiment observation is invalid or belongs to different lineage.');
            }
            $key = $record['case_id'].'::'.$record['variant_id'];
            if (isset($result[$key])) {
                throw new RuntimeException('The durable experiment observations contain a duplicate variant.');
            }
            $result[$key] = $record['observation'];
        }

        return $result;
    }

    /** @param array<string, mixed> $observation */
    public function writeObservation(
        string $runId,
        string $lineageDigest,
        string $caseId,
        string $variantId,
        array $observation,
    ): void {
        $record = [
            'schema_version' => 'v1',
            'lineage_digest' => $lineageDigest,
            'case_id' => $caseId,
            'variant_id' => $variantId,
            'observation' => $observation,
        ];
        $record['record_digest'] = $this->canonical->digest($record);
        $name = hash('sha256', $caseId."\0".$variantId).'.json';
        $this->writeAtomic($this->directory($runId).'/observations/'.$name, $record);
    }

    /** @param array<string, mixed> $payload */
    public function finalise(string $runId, array $payload): string
    {
        $path = $this->directory($runId).'/application-observations.json';
        $this->writeAtomic($path, $payload);

        return $path;
    }

    private function directory(string $runId): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]+$/', $runId) !== 1) {
            throw new RuntimeException('The experiment run identity is invalid.');
        }
        $root = rtrim((string) config('evaluation.runs_root'), '/');
        if ($root === '') {
            throw new RuntimeException('The experiment runs root is unavailable.');
        }

        return $root.'/'.$runId;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path, string $label): array
    {
        try {
            $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("The {$label} is malformed.", 0, $exception);
        }
        if (! is_array($value)) {
            throw new RuntimeException("The {$label} is not an object.");
        }

        return $value;
    }

    /** @param array<string, mixed> $value */
    private function writeAtomic(string $path, array $value): void
    {
        $temporary = $path.'.tmp';
        if (
            file_put_contents($temporary, $this->canonical->encode($value, true), LOCK_EX) === false
            || ! rename($temporary, $path)
        ) {
            @unlink($temporary);
            throw new RuntimeException('Experiment progress could not be persisted atomically.');
        }
    }
}
