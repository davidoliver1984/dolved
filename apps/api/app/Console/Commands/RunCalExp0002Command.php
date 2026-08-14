<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use App\Support\Evaluation\CalExp0002Definition;
use Illuminate\Console\Command;
use Throwable;

final class RunCalExp0002Command extends Command
{
    protected $signature = 'evaluation:benchmark:run-cal-exp-0002 {--repository-commit=}';

    protected $description = 'Run the immutable CAL-EXP-0002 provider pass against only the compatible V3 calibration population';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handleCalExp0002((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s captured %d calibration cases / %d variants at %s (%d resumed).',
            CalExp0002Definition::RUN_ID,
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
