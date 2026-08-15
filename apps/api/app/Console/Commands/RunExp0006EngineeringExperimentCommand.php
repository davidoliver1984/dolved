<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use App\Support\Evaluation\Exp0006Definition;
use Illuminate\Console\Command;
use Throwable;

final class RunExp0006EngineeringExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:run-exp-0006 {--repository-commit=}';

    protected $description = 'Run the immutable ADR-0022-v4 consolidated engineering confirmation';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handleExp0006((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s captured %d engineering cases / %d variants at %s (%d resumed).',
            Exp0006Definition::RUN_ID,
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
