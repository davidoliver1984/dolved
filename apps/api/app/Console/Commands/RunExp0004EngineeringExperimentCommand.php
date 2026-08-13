<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use App\Support\Evaluation\Exp0004Definition;
use Illuminate\Console\Command;
use Throwable;

final class RunExp0004EngineeringExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:run-exp-0004 {--repository-commit=}';

    protected $description = 'Run the immutable EXP-0004 rrf_k=5 treatment against only the V2 engineering split';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handleExp0004((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s captured %d engineering cases / %d variants at %s (%d resumed).',
            Exp0004Definition::RUN_ID,
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
