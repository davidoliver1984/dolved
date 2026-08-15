<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use App\Support\Evaluation\Exp0005Definition;
use Illuminate\Console\Command;
use Throwable;

final class RunExp0005EngineeringExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:run-exp-0005 {--repository-commit=}';

    protected $description = 'Run the immutable ADR-0022-v2 consolidated engineering baseline';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handleExp0005((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s captured %d engineering cases / %d variants at %s (%d resumed).',
            Exp0005Definition::RUN_ID,
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
