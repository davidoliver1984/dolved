<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class ThresholdCalibrationDefinition
{
    public const RUN_ID = 'CAL-EXP-0001-evidence-threshold-calibration';

    public const SPLIT = 'threshold_calibration';

    public const EXPECTED_CASES = 28;

    public const EXPECTED_VARIANTS = 84;

    public const CONTROL_THRESHOLD = 0.337890625;

    /** @return array<string, mixed> */
    public static function lineage(): array
    {
        return [
            'id' => self::RUN_ID,
            'purpose' => 'Execute one provider pass and preserve complete pre-threshold reranker lineage for provider-free calibration replay.',
            'split' => [
                'name' => self::SPLIT,
                'case_count' => self::EXPECTED_CASES,
                'variant_count' => self::EXPECTED_VARIANTS,
            ],
            'candidate_pipeline' => [
                'dense_candidate_k' => 40,
                'sparse_candidate_k' => 40,
                'modality_weights' => ['dense' => 1, 'sparse' => 1],
                'rrf_k' => 5,
                'fusion_candidate_k' => 15,
                'reranker_candidate_k' => 15,
                'factual_control_threshold' => self::CONTROL_THRESHOLD,
                'final_evidence_k' => 5,
            ],
            'provider_execution' => 'single_pass',
            'threshold_replay' => 'provider_free_from_complete_pre_threshold_lineage',
        ];
    }

    private function __construct() {}
}
