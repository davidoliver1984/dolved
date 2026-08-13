<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\ThresholdCalibrationDefinition;
use Tests\TestCase;

final class ThresholdCalibrationDefinitionTest extends TestCase
{
    public function test_definition_freezes_the_calibration_split_and_retrieval_configuration(): void
    {
        $lineage = ThresholdCalibrationDefinition::lineage();

        $this->assertSame('CAL-EXP-0001-evidence-threshold-calibration', $lineage['id']);
        $this->assertSame([
            'name' => 'threshold_calibration',
            'case_count' => 28,
            'variant_count' => 84,
        ], $lineage['split']);
        $this->assertSame([
            'dense_candidate_k' => 40,
            'sparse_candidate_k' => 40,
            'modality_weights' => ['dense' => 1, 'sparse' => 1],
            'rrf_k' => 5,
            'fusion_candidate_k' => 15,
            'reranker_candidate_k' => 15,
            'factual_control_threshold' => 0.337890625,
            'final_evidence_k' => 5,
        ], $lineage['candidate_pipeline']);
        $this->assertSame('/evaluation/calibration/corpus.json', EngineeringBenchmark::CALIBRATION_SNAPSHOT);
    }

    public function test_calibration_command_has_no_dirty_or_split_override(): void
    {
        $command = file_get_contents(app_path('Console/Commands/RunThresholdCalibrationExperimentCommand.php'));
        $action = file_get_contents(app_path('Actions/Evaluation/RunEngineeringBenchmarkExperiment.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString(
            'evaluation:benchmark:run-threshold-calibration {--repository-commit=}',
            $command,
        );
        $this->assertStringNotContainsString('--dirty', $command);
        $this->assertStringNotContainsString('--split', $command);
        $this->assertIsString($action);
        $this->assertStringContainsString('$this->source->calibrationCorpus()', $action);
    }
}
