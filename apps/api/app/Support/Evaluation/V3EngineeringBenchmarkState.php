<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use JsonException;
use RuntimeException;

final readonly class V3EngineeringBenchmarkState
{
    public function __construct(
        private FilesystemFactory $filesystems,
        private BenchmarkCanonicalJson $canonical,
    ) {}

    public function exists(): bool
    {
        return $this->filesystems->disk('local')->exists(V3EngineeringBenchmark::STATE_PATH);
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        $raw = $this->filesystems->disk('local')->get(V3EngineeringBenchmark::STATE_PATH);
        try {
            $state = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The V3 engineering provisioning record is invalid.', 0, $exception);
        }
        if (! is_array($state)) {
            throw new RuntimeException('The V3 engineering provisioning record is not an object.');
        }
        $recorded = $state['mapping_digest'] ?? null;
        unset($state['mapping_digest']);
        if (! is_string($recorded) || ! hash_equals($recorded, $this->canonical->digest($state))) {
            throw new RuntimeException('The V3 engineering provisioning record digest does not match its content.');
        }
        $state['mapping_digest'] = $recorded;

        return $state;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function write(array $state): array
    {
        unset($state['mapping_digest']);
        $state['mapping_digest'] = $this->canonical->digest($state);
        $disk = $this->filesystems->disk('local');
        $temporary = V3EngineeringBenchmark::STATE_PATH.'.tmp';
        if (! $disk->put($temporary, $this->canonical->encode($state, true)) || ! $disk->move($temporary, V3EngineeringBenchmark::STATE_PATH)) {
            throw new RuntimeException('The V3 engineering provisioning record could not be persisted atomically.');
        }

        return $state;
    }
}
