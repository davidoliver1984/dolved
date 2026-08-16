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

    /** @return array<string, true> */
    public function completedIdentities(string $runId, string $lineageDigest): array
    {
        $result = [];
        foreach (glob($this->directory($runId).'/observations/*.json') ?: [] as $path) {
            $record = $this->readValidatedRecord($path, $lineageDigest);
            $key = $record['case_id'].'::'.$record['variant_id'];
            if (isset($result[$key])) {
                throw new RuntimeException('The durable experiment observations contain a duplicate variant.');
            }
            $result[$key] = true;
            unset($record);
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
        $path = $this->observationPath($runId, $caseId, $variantId);
        if (is_file($path)) {
            $existing = $this->readValidatedRecord($path, $lineageDigest);
            if (
                $existing['case_id'] !== $caseId
                || $existing['variant_id'] !== $variantId
                || $existing['observation'] !== $observation
            ) {
                throw new RuntimeException('A completed experiment observation cannot be replaced.');
            }

            return;
        }
        $record = [
            'schema_version' => 'v1',
            'lineage_digest' => $lineageDigest,
            'case_id' => $caseId,
            'variant_id' => $variantId,
            'observation' => $observation,
        ];
        $record['record_digest'] = $this->canonical->digest($record);
        $this->writeAtomic($path, $record);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array{case_id: string, variant_id: string}>  $orderedIdentities
     */
    public function finaliseFromCheckpoints(
        string $runId,
        string $lineageDigest,
        array $header,
        array $orderedIdentities,
    ): string {
        $path = $this->directory($runId).'/application-observations.json';
        $temporary = $path.'.tmp';
        $handle = fopen($temporary, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Experiment finalisation could not be started.');
        }
        try {
            $members = array_keys($header);
            $members[] = 'observations';
            sort($members, SORT_STRING);
            $this->writeStream($handle, '{');
            $firstMember = true;
            foreach ($members as $member) {
                $this->writeStream($handle, ($firstMember ? '' : ',').json_encode(
                    $member,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ).':');
                $firstMember = false;
                if ($member !== 'observations') {
                    $this->writeStream($handle, $this->encodeValue($header[$member]));

                    continue;
                }
                $this->writeStream($handle, '[');
                foreach ($orderedIdentities as $index => $identity) {
                    $record = $this->readValidatedRecord(
                        $this->observationPath(
                            $runId,
                            $identity['case_id'],
                            $identity['variant_id'],
                        ),
                        $lineageDigest,
                    );
                    if (
                        $record['case_id'] !== $identity['case_id']
                        || $record['variant_id'] !== $identity['variant_id']
                    ) {
                        throw new RuntimeException('Checkpoint identity changed during finalisation.');
                    }
                    $this->writeStream(
                        $handle,
                        ($index === 0 ? '' : ',').$this->canonical->encode($record['observation']),
                    );
                    unset($record);
                }
                $this->writeStream($handle, ']');
            }
            $this->writeStream($handle, "}\n");
            if (! fflush($handle) || (function_exists('fsync') && ! fsync($handle))) {
                throw new RuntimeException('Experiment finalisation could not be flushed.');
            }
        } finally {
            fclose($handle);
        }
        if (is_file($path)) {
            if (! hash_equals(hash_file('sha256', $path), hash_file('sha256', $temporary))) {
                @unlink($temporary);
                throw new RuntimeException('A completed experiment artefact cannot be replaced.');
            }
            @unlink($temporary);

            return $path;
        }
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Experiment finalisation could not be published atomically.');
        }

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

    /** @return array{schema_version: string, lineage_digest: string, case_id: string, variant_id: string, observation: array<string, mixed>} */
    private function readValidatedRecord(string $path, string $lineageDigest): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('An expected durable experiment observation is missing.');
        }
        $record = $this->readJson($path, 'experiment variant observation');
        $recordedDigest = $record['record_digest'] ?? null;
        unset($record['record_digest']);
        $caseId = $record['case_id'] ?? null;
        $variantId = $record['variant_id'] ?? null;
        $observation = $record['observation'] ?? null;
        if (
            ($record['schema_version'] ?? null) !== 'v1'
            || ! is_string($recordedDigest)
            || ! hash_equals($recordedDigest, $this->canonical->digest($record))
            || ($record['lineage_digest'] ?? null) !== $lineageDigest
            || ! is_string($caseId)
            || ! is_string($variantId)
            || ! is_array($observation)
            || ! $this->validObservation($observation, $caseId, $variantId)
            || basename($path) !== hash('sha256', $caseId."\0".$variantId).'.json'
        ) {
            throw new RuntimeException('A durable experiment observation is invalid or belongs to different lineage.');
        }

        /** @var array{schema_version: string, lineage_digest: string, case_id: string, variant_id: string, observation: array<string, mixed>} $record */
        return $record;
    }

    /** @param array<string, mixed> $observation */
    private function validObservation(array $observation, string $caseId, string $variantId): bool
    {
        $planning = $observation['planning'] ?? null;
        $retrievalExecuted = $observation['retrieval_executed'] ?? null;

        return is_array($observation['case'] ?? null)
            && ($observation['case']['case_id'] ?? null) === $caseId
            && is_array($observation['variant'] ?? null)
            && ($observation['variant']['variant_id'] ?? null) === $variantId
            && is_numeric($observation['latency_ms'] ?? null)
            && $observation['latency_ms'] >= 0
            && is_string($observation['observed_at'] ?? null)
            && is_array($planning)
            && in_array($planning['status'] ?? null, ['succeeded', 'failed'], true)
            && is_bool($retrievalExecuted)
            && (
                ($retrievalExecuted
                    && is_array($observation['dense'] ?? null)
                    && is_array($observation['hybrid'] ?? null))
                || (! $retrievalExecuted
                    && ($observation['dense'] ?? null) === null
                    && ($observation['hybrid'] ?? null) === null)
            );
    }

    private function observationPath(string $runId, string $caseId, string $variantId): string
    {
        return $this->directory($runId).'/observations/'.hash(
            'sha256',
            $caseId."\0".$variantId,
        ).'.json';
    }

    private function encodeValue(mixed $value): string
    {
        if (is_array($value)) {
            return $this->canonical->encode($value);
        }

        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @param resource $handle */
    private function writeStream($handle, string $value): void
    {
        $length = strlen($value);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($value, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Experiment finalisation could not be written.');
            }
            $written += $bytes;
        }
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
