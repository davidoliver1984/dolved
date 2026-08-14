<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use App\Support\Evaluation\CalExp0003Definition;
use Illuminate\Console\Command;
use Throwable;

final class RunCalExp0003Command extends Command
{
    protected $signature = 'evaluation:benchmark:run-cal-exp-0003 {--repository-commit=}';

    protected $description = 'Run the immutable post-planner-hardening calibration pass against only the compatible V3 population';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handleCalExp0003((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s captured %d calibration cases / %d variants at %s (%d resumed).',
            CalExp0003Definition::RUN_ID,
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
