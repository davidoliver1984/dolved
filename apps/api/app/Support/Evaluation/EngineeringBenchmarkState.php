<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use JsonException;
use RuntimeException;

final readonly class EngineeringBenchmarkState
{
    public function __construct(
        private FilesystemFactory $filesystems,
        private BenchmarkCanonicalJson $canonical,
    ) {}

    public function exists(): bool
    {
        return $this->filesystems->disk('local')->exists(EngineeringBenchmark::STATE_PATH);
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        $raw = $this->filesystems->disk('local')->get(EngineeringBenchmark::STATE_PATH);
        try {
            $state = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The benchmark provisioning record is invalid.', 0, $exception);
        }
        if (! is_array($state)) {
            throw new RuntimeException('The benchmark provisioning record is not an object.');
        }
        $recordedDigest = $state['mapping_digest'] ?? null;
        unset($state['mapping_digest']);
        if (! is_string($recordedDigest) || ! hash_equals($recordedDigest, $this->canonical->digest($state))) {
            throw new RuntimeException('The benchmark provisioning record digest does not match its content.');
        }
        $state['mapping_digest'] = $recordedDigest;

        return $state;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function write(array $state): array
    {
        unset($state['mapping_digest']);
        $state['mapping_digest'] = $this->canonical->digest($state);
        $disk = $this->filesystems->disk('local');
        $temporary = EngineeringBenchmark::STATE_PATH.'.tmp';
        if (! $disk->put($temporary, $this->canonical->encode($state, true)) || ! $disk->move($temporary, EngineeringBenchmark::STATE_PATH)) {
            throw new RuntimeException('The benchmark provisioning record could not be persisted atomically.');
        }

        return $state;
    }

    public function delete(): void
    {
        $this->filesystems->disk('local')->delete(EngineeringBenchmark::STATE_PATH);
    }
}
