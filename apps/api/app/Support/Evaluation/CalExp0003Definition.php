<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class CalExp0003Definition
{
    public const RUN_ID = 'CAL-EXP-0003-v3-post-planner-hardening-calibration';

    public const SPLIT = 'threshold_calibration';

    public const SPLIT_VERSION = '2';

    public const EXPECTED_CASES = 44;

    public const EXPECTED_VARIANTS = 132;

    public const BENCHMARK_VERSION = '3';

    public const BENCHMARK_DIGEST = '8a90b0e6da531223ab6f113f237c2bde3149690662a5a4f1f3cbd5f921c0acd7';

    public const EVALUATION_CLOCK = '2026-08-01T12:00:00Z';

    public const POPULATION_DIGEST = 'bafc720dbf03aba9e3fdee597ba0b9f2bfaa38db5adc8502b88bf68d07c57345';

    public const CASE_IDS_DIGEST = '1808477d771b24f45b726dd4b05ce8defa5b1edc624b8907155ccac7ab7e4801';

    public const COMPATIBILITY_RESULT_DIGEST = 'b86c329955bafee702b02a8fc695e35ab22306cb88c1c1979215ae6c95725670';

    public const COMPATIBILITY_POLICY_DIGEST = '342521355b236b4d64d7803a82f44f5fdc06325c3183a972a7cc05034136445d';

    public const CONTROL_THRESHOLD = 0.337890625;

    public const PLANNER_FINGERPRINT = '114789559d7032cefb4e93d1134ce3a4e2234a0db9c26048940cbb1d095758bd';

    /** @return array<string, string> */
    public static function planner(): array
    {
        return [
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'contract_schema_version' => 'plan-response-v2',
            'prompt_version' => 'adr-0022-v1',
            'adapter_version' => 'structured-chat-v3',
            'fingerprint' => self::PLANNER_FINGERPRINT,
        ];
    }

    /** @return array<string, mixed> */
    public static function lineage(): array
    {
        return [
            'id' => self::RUN_ID,
            'purpose' => 'Repeat the compatible Benchmark V3 calibration provider pass after the accepted planner typed-output hardening.',
            'predecessor' => [
                'id' => CalExp0002Definition::RUN_ID,
                'disposition' => 'immutable_failed_closed',
                'reason' => 'planner_failure_before_threshold',
            ],
            'benchmark' => [
                'id' => EngineeringBenchmark::ID,
                'version' => self::BENCHMARK_VERSION,
                'digest' => self::BENCHMARK_DIGEST,
                'evaluation_clock' => self::EVALUATION_CLOCK,
            ],
            'population' => [
                'id' => 'dolved-care-engineering-threshold_calibration-2',
                'digest' => self::POPULATION_DIGEST,
                'case_ids_digest' => self::CASE_IDS_DIGEST,
                'case_count' => self::EXPECTED_CASES,
                'variant_count' => self::EXPECTED_VARIANTS,
            ],
            'compatibility' => [
                'result_digest' => self::COMPATIBILITY_RESULT_DIGEST,
                'requirements_digest' => self::COMPATIBILITY_POLICY_DIGEST,
            ],
            'planner' => self::planner(),
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
            'provider_execution' => 'single_pass_no_selective_retry',
            'threshold_replay' => 'provider_free_from_complete_pre_threshold_lineage',
        ];
    }

    private function __construct() {}
}
