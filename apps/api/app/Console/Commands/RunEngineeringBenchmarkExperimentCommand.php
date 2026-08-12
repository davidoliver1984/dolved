<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use Illuminate\Console\Command;
use Throwable;

final class RunEngineeringBenchmarkExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:run-exp-0002 {--repository-commit=} {--dirty=0}';

    protected $description = 'Run EXP-0002 through the ADR-0022 production path against only the V2 engineering split';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handle(
                (string) $this->option('repository-commit'),
                filter_var($this->option('dirty'), FILTER_VALIDATE_BOOL),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            'EXP-0002 captured %d engineering cases / %d variants at %s (%d resumed).',
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
