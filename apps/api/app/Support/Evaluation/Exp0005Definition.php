<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use RuntimeException;

final class Exp0005Definition
{
    public const RUN_ID = 'EXP-0005-adr0022-v2-consolidated-engineering-baseline';

    public const PURPOSE = 'Measure the complete engineering population after the accepted ADR-0022-v2 planner and benchmark corrections.';

    public const PLANNER_FINGERPRINT = '77d052cff157f679cc374b1fba86bb32790e17815051e2f24f12a97ceb751d30';

    public const POLICY_FINGERPRINT = '6626d78bd9445c70fd946a64b0a817b4e77b264a14d945d483ba497f9e681364';

    public const PROVISIONING_RECORD_SHA256 = '8691c5aa5a40b1aeba527287226c3d41515c576eff6ff4b216d1e068bc3f370d';

    public const PROVISIONING_MAPPING_DIGEST = '1c206bcf8ddf3021502fa5ebb46ee696bf1faf159d1cbfc82d514d08913ac338';

    public const ACTIVE_GENERATION_PUBLIC_ID = 'a6e60b44-4f77-45a8-ae01-677b5516a7eb';

    public const POINT_MANIFEST_DIGEST = '7cb89453fbf90e6e45d24454ec3d4e4f801774404ee90caae4b959a76f664ab0';

    public const EMBEDDING_PROFILE_FINGERPRINT = 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c';

    public const SPARSE_PROFILE_FINGERPRINT = 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d';

    /** @return array<string, string> */
    public static function planner(): array
    {
        return [
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'contract_schema_version' => 'plan-response-v2',
            'prompt_version' => 'adr-0022-v2',
            'adapter_version' => 'structured-chat-v3',
            'fingerprint' => self::PLANNER_FINGERPRINT,
        ];
    }

    /** @return array<string, mixed> */
    public static function lineage(): array
    {
        return [
            'id' => self::RUN_ID,
            'purpose' => self::PURPOSE,
            'engineering_split' => [
                'name' => 'engineering_tuning',
                'case_count' => EngineeringBenchmark::EXPECTED_ENGINEERING_CASES,
                'variant_count' => EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS,
                'snapshot_digest' => '8f67bb00ad22fe8f74ecdc834f66f22a00bf97bffe409d6857ce44fc0a0a5de5',
                'case_ids_digest' => EngineeringBenchmark::ENGINEERING_CASE_IDS_DIGEST,
            ],
            'planner' => self::planner(),
            'retrieval' => [
                'dense' => ['provider' => 'voyage', 'model' => 'voyage-4-large', 'dimensions' => 1024, 'candidate_k' => 40],
                'sparse' => ['provider' => 'fastembed', 'model' => 'prithivida/Splade_PP_en_v1', 'candidate_k' => 40],
                'fusion' => ['strategy' => 'rrf', 'weighting' => 'equal', 'rrf_k' => 5, 'candidate_k' => 15],
                'reranker' => ['provider' => 'voyage', 'model' => 'rerank-2.5', 'candidate_k' => 15],
                'evidence_threshold' => 0.337890625,
                'final_evidence_k' => 5,
                'policy_fingerprint' => self::POLICY_FINGERPRINT,
            ],
            'provisioning' => [
                'record_sha256' => self::PROVISIONING_RECORD_SHA256,
                'mapping_digest' => self::PROVISIONING_MAPPING_DIGEST,
                'active_generation_public_id' => self::ACTIVE_GENERATION_PUBLIC_ID,
                'point_manifest_digest' => self::POINT_MANIFEST_DIGEST,
                'expected_point_count' => 99,
                'embedding_profile_fingerprint' => self::EMBEDDING_PROFILE_FINGERPRINT,
                'sparse_profile_fingerprint' => self::SPARSE_PROFILE_FINGERPRINT,
            ],
        ];
    }

    public static function assertPolicy(EvidenceThresholdPolicy $policy): void
    {
        if (
            $policy->status !== EvidenceThresholdPolicyStatus::Calibrating
            || ! hash_equals(self::POLICY_FINGERPRINT, $policy->fingerprint)
        ) {
            throw new RuntimeException(self::RUN_ID.' requires the frozen engineering policy.');
        }
    }

    /** @param array<string, string> $planner */
    public static function assertPlanner(array $planner): void
    {
        if ($planner !== self::planner()) {
            throw new RuntimeException(self::RUN_ID.' requires the approved ADR-0022-v2 planner lineage.');
        }
    }

    /** @param array<string, mixed> $state */
    public static function assertProvisioning(array $state): void
    {
        $generation = $state['generations']['hybrid_corpus'] ?? [];
        $embedding = $state['generations']['embedding_space'] ?? [];
        $sparse = $state['generations']['sparse_space'] ?? [];
        if (
            ($state['mapping_digest'] ?? null) !== self::PROVISIONING_MAPPING_DIGEST
            || ($generation['public_id'] ?? null) !== self::ACTIVE_GENERATION_PUBLIC_ID
            || ($generation['expected_point_count'] ?? null) !== 99
            || ($generation['point_manifest_digest'] ?? null) !== self::POINT_MANIFEST_DIGEST
            || ($embedding['profile_fingerprint'] ?? null) !== self::EMBEDDING_PROFILE_FINGERPRINT
            || ($sparse['profile_fingerprint'] ?? null) !== self::SPARSE_PROFILE_FINGERPRINT
        ) {
            throw new RuntimeException(self::RUN_ID.' provisioning lineage does not match the immutable definition.');
        }
    }

    private function __construct() {}
}
