<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunEngineeringBenchmarkExperiment;
use App\Support\Evaluation\ThresholdCalibrationDefinition;
use Illuminate\Console\Command;
use Throwable;

final class RunThresholdCalibrationExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:run-threshold-calibration {--repository-commit=}';

    protected $description = 'Run one immutable provider pass against only the V2 threshold-calibration split';

    public function handle(RunEngineeringBenchmarkExperiment $run): int
    {
        try {
            $result = $run->handleThresholdCalibration((string) $this->option('repository-commit'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s captured %d calibration cases / %d variants at %s (%d resumed).',
            ThresholdCalibrationDefinition::RUN_ID,
            $result['case_count'],
            $result['variant_count'],
            $result['path'],
            $result['resumed_count'],
        ));

        return self::SUCCESS;
    }
}
