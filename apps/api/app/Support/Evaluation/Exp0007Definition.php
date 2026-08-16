<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use RuntimeException;

final class Exp0007Definition
{
    public const RUN_ID = 'EXP-0007-v3-engineering-regression-confirmation';

    public const PLANNER_FINGERPRINT = '063d8b87bd0351179410b433ed6b0400de50d76dd67ae6f9ceb27a572386f9b8';

    public const POLICY_FINGERPRINT = '6626d78bd9445c70fd946a64b0a817b4e77b264a14d945d483ba497f9e681364';

    public const POPULATION_MANIFEST_SHA256 = '1e87545d69d027a0fd7c21de20422294d9b3d75fa21cfdf86a9e49ed9b098c8d';

    public const CORPUS_SHA256 = 'a789e17a8b302194da7f80cf8ba59e2551734f8cefa4fb75f7da197f71c3b10f';

    public const EXPECTATIONS_SHA256 = 'acd1c1f0e4869921fa86cd147a5c24ff3877026388309f2f1dd1aa5048bf74da';

    public const ORGANISATION_SHA256 = '9bcfefa56b05d0e0eadadcd3b4c1dbc1e0e2d6b05d7f086b8e4098c6289fae09';

    public const CATALOGUE_SHA256 = 'c234e38c559f69fba6be1726a2bf32835a776dabd66ca20ee2ec496e70f8bba8';

    public const PROVISIONING_RECORD_SHA256 = 'b0aafb2a27670e673ff391947e3672a2893c94b86eccd699d007f4ad6c241b19';

    public const PROVISIONING_MAPPING_DIGEST = '942e3260ef8e7f85d786eea2534fa5240ae27dc94f61bfc17208bc99fbcacb5e';

    public const ACTIVE_GENERATION_PUBLIC_ID = '289ccffe-2264-4867-aa1e-d8eb1af43300';

    public const POINT_MANIFEST_DIGEST = 'f7784417846affca3a86a18fe84426de1d4e21e2736cebd63d5320ddc91650c5';

    public const EMBEDDING_PROFILE_FINGERPRINT = 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c';

    public const SPARSE_PROFILE_FINGERPRINT = 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d';

    /** @return array<string, string> */
    public static function planner(): array
    {
        return [
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'contract_schema_version' => 'plan-response-v2',
            'prompt_version' => 'adr-0022-v4',
            'adapter_version' => 'structured-chat-v3',
            'fingerprint' => self::PLANNER_FINGERPRINT,
        ];
    }

    /** @return array<string, mixed> */
    public static function lineage(): array
    {
        return [
            'id' => self::RUN_ID,
            'benchmark' => [
                'id' => V3EngineeringBenchmark::ID,
                'version' => V3EngineeringBenchmark::VERSION,
                'authoring_digest' => V3EngineeringBenchmark::BENCHMARK_AUTHORING_DIGEST,
            ],
            'engineering_population' => [
                'id' => V3EngineeringBenchmark::POPULATION_ID,
                'digest' => V3EngineeringBenchmark::POPULATION_DIGEST,
                'manifest_sha256' => self::POPULATION_MANIFEST_SHA256,
                'corpus_sha256' => self::CORPUS_SHA256,
                'expectations_sha256' => self::EXPECTATIONS_SHA256,
                'case_count' => V3EngineeringBenchmark::EXPECTED_CASES,
                'variant_count' => V3EngineeringBenchmark::EXPECTED_VARIANTS,
            ],
            'organisation_sha256' => self::ORGANISATION_SHA256,
            'catalogue_sha256' => self::CATALOGUE_SHA256,
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
                'expected_point_count' => 100,
                'embedding_profile_fingerprint' => self::EMBEDDING_PROFILE_FINGERPRINT,
                'sparse_profile_fingerprint' => self::SPARSE_PROFILE_FINGERPRINT,
            ],
        ];
    }

    public static function assertPolicy(EvidenceThresholdPolicy $policy): void
    {
        if ($policy->status !== EvidenceThresholdPolicyStatus::Calibrating || ! hash_equals(self::POLICY_FINGERPRINT, $policy->fingerprint)) {
            throw new RuntimeException(self::RUN_ID.' requires the frozen unpromoted engineering policy.');
        }
    }

    /** @param array<string, string> $planner */
    public static function assertPlanner(array $planner): void
    {
        if ($planner !== self::planner()) {
            throw new RuntimeException(self::RUN_ID.' requires the approved ADR-0022-v4 planner lineage.');
        }
    }

    /** @param array<string, mixed> $state */
    public static function assertProvisioning(array $state): void
    {
        $generation = $state['generations']['hybrid_corpus'] ?? [];
        $embedding = $state['generations']['embedding_space'] ?? [];
        $sparse = $state['generations']['sparse_space'] ?? [];
        if (
            ($state['status'] ?? null) !== 'MATERIALISED'
            || ($state['mapping_digest'] ?? null) !== self::PROVISIONING_MAPPING_DIGEST
            || ($state['benchmark']['population_digest'] ?? null) !== V3EngineeringBenchmark::POPULATION_DIGEST
            || ($generation['public_id'] ?? null) !== self::ACTIVE_GENERATION_PUBLIC_ID
            || ($generation['expected_point_count'] ?? null) !== 100
            || ($generation['actual_point_count'] ?? null) !== 100
            || ($generation['point_manifest_digest'] ?? null) !== self::POINT_MANIFEST_DIGEST
            || ($embedding['profile_fingerprint'] ?? null) !== self::EMBEDDING_PROFILE_FINGERPRINT
            || ($sparse['profile_fingerprint'] ?? null) !== self::SPARSE_PROFILE_FINGERPRINT
        ) {
            throw new RuntimeException(self::RUN_ID.' provisioning lineage does not match the immutable V3 definition.');
        }
    }

    private function __construct() {}
}
