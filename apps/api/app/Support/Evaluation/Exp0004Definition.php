<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use RuntimeException;

final class Exp0004Definition
{
    public const RUN_ID = 'EXP-0004-rrf-k-5-controlled-engineering-experiment';

    public const PURPOSE = 'Test rrf_k=5 end to end against the immutable EXP-0003 control with every other retrieval value frozen.';

    public const CONTROL_RUN_ID = 'EXP-0003-post-reliability-corrected-engineering-baseline';

    public const CONTROL_POLICY_FINGERPRINT = '11386497e6316bf199abb75ad0c6ca8baaafe759c297d5044dfc7ce07630eb21';

    public const TREATMENT_POLICY_PUBLIC_ID = 'b01afcee-933f-5e90-9671-0f0c5e13d391';

    public const TREATMENT_POLICY_VERSION = 'exp-0004-rrf-k-5-controlled-engineering';

    public const CONTROL_RRF_K = 60;

    public const TREATMENT_RRF_K = 5;

    /** @var array<string, int|float|string> */
    private const FROZEN_CONTROL = [
        'reranker_provider' => 'voyage',
        'reranker_model' => 'rerank-2.5',
        'reranker_adapter_version' => '1',
        'fusion_strategy' => 'rrf',
        'fusion_version' => '1',
        'rrf_k' => self::CONTROL_RRF_K,
        'dense_candidate_k' => 40,
        'sparse_candidate_k' => 40,
        'fusion_candidate_k' => 15,
        'reranker_candidate_k' => 15,
        'evidence_threshold' => 0.337890625,
        'final_evidence_k' => 5,
        'calibration_corpus_version' => 'v2-foundation-experimental',
        'calibration_corpus_digest' => EngineeringBenchmark::DIGEST,
    ];

    /** @return array<string, mixed> */
    public static function lineage(): array
    {
        return [
            'id' => self::RUN_ID,
            'purpose' => self::PURPOSE,
            'control_experiment_id' => self::CONTROL_RUN_ID,
            'sole_retrieval_variable' => [
                'name' => 'rrf_k',
                'control' => self::CONTROL_RRF_K,
                'treatment' => self::TREATMENT_RRF_K,
            ],
            'engineering_split' => [
                'name' => 'engineering_tuning',
                'case_count' => EngineeringBenchmark::EXPECTED_ENGINEERING_CASES,
                'variant_count' => EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS,
                'case_ids_digest' => EngineeringBenchmark::ENGINEERING_CASE_IDS_DIGEST,
            ],
            'control_policy_fingerprint' => self::CONTROL_POLICY_FINGERPRINT,
        ];
    }

    public static function treatmentPolicy(
        EvidenceThresholdPolicy $control,
        BenchmarkCanonicalJson $canonical,
    ): EvidenceThresholdPolicy {
        self::assertControl($control);
        $identity = $control->only([
            'version',
            'reranker_provider',
            'reranker_model',
            'reranker_adapter_version',
            'embedding_profile_fingerprint',
            'sparse_profile_fingerprint',
            'fusion_strategy',
            'fusion_version',
            'rrf_k',
            'dense_candidate_k',
            'sparse_candidate_k',
            'fusion_candidate_k',
            'reranker_candidate_k',
            'evidence_threshold',
            'final_evidence_k',
            'calibration_corpus_version',
            'calibration_corpus_digest',
        ]);
        $identity['version'] = self::TREATMENT_POLICY_VERSION;
        $identity['rrf_k'] = self::TREATMENT_RRF_K;
        $policy = new EvidenceThresholdPolicy($identity + [
            'status' => EvidenceThresholdPolicyStatus::Calibrating,
            'fingerprint' => $canonical->digest($identity),
        ]);
        $policy->public_id = self::TREATMENT_POLICY_PUBLIC_ID;

        return $policy;
    }

    private static function assertControl(EvidenceThresholdPolicy $control): void
    {
        if (
            $control->status !== EvidenceThresholdPolicyStatus::Calibrating
            || ! hash_equals(self::CONTROL_POLICY_FINGERPRINT, $control->fingerprint)
        ) {
            throw new RuntimeException(self::RUN_ID.' requires the immutable EXP-0003 control policy.');
        }
        foreach (self::FROZEN_CONTROL as $field => $expected) {
            if ($control->getAttribute($field) !== $expected) {
                throw new RuntimeException(self::RUN_ID." control policy differs at {$field}.");
            }
        }
    }

    private function __construct() {}
}
