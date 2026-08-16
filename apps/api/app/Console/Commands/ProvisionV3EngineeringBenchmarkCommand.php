<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\ProvisionV3EngineeringBenchmark;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class ProvisionV3EngineeringBenchmarkCommand extends Command
{
    protected $signature = 'evaluation:v3-engineering:provision {--repository-commit=}';

    protected $description = 'Materialise and queue the immutable Benchmark V3 engineering definition';

    public function handle(ProvisionV3EngineeringBenchmark $provision): int
    {
        try {
            if (Artisan::call('ingestion:provision-embedding-space') !== 0) {
                throw new \RuntimeException('Dense embedding-space provisioning failed.');
            }
            $state = $provision->handle((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf('Benchmark V3 is %s; mapping digest %s.', $state['status'], $state['mapping_digest']));

        return self::SUCCESS;
    }
}
