<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use App\Support\Evaluation\Exp0008Definition;
use Illuminate\Console\Command;
use Throwable;

final class RunExp0008EngineeringExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:run-exp-0008 {--repository-commit=}';

    protected $description = 'Run the immutable final V3 engineering confirmation';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handleExp0008((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s captured %d engineering cases / %d variants at %s (%d resumed).',
            Exp0008Definition::RUN_ID,
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
