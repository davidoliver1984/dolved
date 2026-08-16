<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use RuntimeException;

final class Exp0008Definition
{
    public const RUN_ID = 'EXP-0008-v3-final-engineering-confirmation';

    public const PLANNER_FINGERPRINT = 'b18ce9cfcb769bbe2c2d28e74ba9b1ffa90a62c887de7e9b04d595cc6a1cf690';

    public const POLICY_FINGERPRINT = Exp0007Definition::POLICY_FINGERPRINT;

    public const POPULATION_MANIFEST_SHA256 = 'd1dd1df24350013ec4ad6364e0344b5d57a02650fbb445f3dce86568d2f7aa68';

    public const CORPUS_SHA256 = '4ebeb404dc7ccae7d8a677a77702aa5e1c043dfa78621a391c5176f39480e493';

    public const EXPECTATIONS_SHA256 = 'acd1c1f0e4869921fa86cd147a5c24ff3877026388309f2f1dd1aa5048bf74da';

    public const CASE_IDS_DIGEST = '5be81b3238889f6b68af049e37a28f8de1cf00b4ab7e4883b2e59d630e9dcfbf';

    public const VARIANT_IDENTITIES_DIGEST = '652f3620060c8f2aa3c692a036ffa7a6ea7d5be9c20538ac29e78402392571ee';

    public const ORGANISATION_SHA256 = Exp0007Definition::ORGANISATION_SHA256;

    public const CATALOGUE_SHA256 = Exp0007Definition::CATALOGUE_SHA256;

    public const PROVISIONING_DEFINITION_SHA256 = '2d9e99817e42e5c673d1448940f4b7e7f52db72157a783520c819217797a5b39';

    public const SOURCE_CATALOG_DIGEST = '4b6864760d735f5b59ec7027e2e6006d21435fb738d366d5d52e78fd48a3ae6e';

    public const PROVISIONING_RECORD_SHA256 = Exp0007Definition::PROVISIONING_RECORD_SHA256;

    public const PROVISIONING_MAPPING_DIGEST = Exp0007Definition::PROVISIONING_MAPPING_DIGEST;

    public const MATERIALISED_POPULATION_DIGEST = 'faac5aa922671d13402fefc75b0c1e613f9edd8fc90bf7e9812b4bf3d14f5d6a';

    public const ACTIVE_GENERATION_PUBLIC_ID = Exp0007Definition::ACTIVE_GENERATION_PUBLIC_ID;

    public const POINT_MANIFEST_DIGEST = Exp0007Definition::POINT_MANIFEST_DIGEST;

    public const EMBEDDING_PROFILE_FINGERPRINT = Exp0007Definition::EMBEDDING_PROFILE_FINGERPRINT;

    public const SPARSE_PROFILE_FINGERPRINT = Exp0007Definition::SPARSE_PROFILE_FINGERPRINT;

    /** @return array<string, string> */
    public static function planner(): array
    {
        return [
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'contract_schema_version' => 'plan-response-v2',
            'prompt_version' => 'adr-0022-v5',
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
                'id' => Exp0008EngineeringBenchmark::ID,
                'version' => Exp0008EngineeringBenchmark::VERSION,
                'authoring_digest' => Exp0008EngineeringBenchmark::BENCHMARK_AUTHORING_DIGEST,
            ],
            'engineering_population' => [
                'id' => Exp0008EngineeringBenchmark::POPULATION_ID,
                'digest' => Exp0008EngineeringBenchmark::POPULATION_DIGEST,
                'manifest_sha256' => self::POPULATION_MANIFEST_SHA256,
                'corpus_sha256' => self::CORPUS_SHA256,
                'expectations_sha256' => self::EXPECTATIONS_SHA256,
                'case_ids_digest' => self::CASE_IDS_DIGEST,
                'variant_identities_digest' => self::VARIANT_IDENTITIES_DIGEST,
                'case_count' => Exp0008EngineeringBenchmark::EXPECTED_CASES,
                'variant_count' => Exp0008EngineeringBenchmark::EXPECTED_VARIANTS,
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
            'materialisation_reuse' => [
                'record_sha256' => self::PROVISIONING_RECORD_SHA256,
                'mapping_digest' => self::PROVISIONING_MAPPING_DIGEST,
                'materialised_population_digest' => self::MATERIALISED_POPULATION_DIGEST,
                'current_source_catalog_digest' => self::SOURCE_CATALOG_DIGEST,
                'current_provisioning_definition_sha256' => self::PROVISIONING_DEFINITION_SHA256,
                'reason' => 'Only engineering question/review lineage changed; organisation, catalogue and all ingestion sources are unchanged.',
            ],
            'active_generation' => [
                'public_id' => self::ACTIVE_GENERATION_PUBLIC_ID,
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
            throw new RuntimeException(self::RUN_ID.' requires the approved ADR-0022-v5 planner lineage.');
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
            || ($state['benchmark']['population_digest'] ?? null) !== self::MATERIALISED_POPULATION_DIGEST
            || ($generation['public_id'] ?? null) !== self::ACTIVE_GENERATION_PUBLIC_ID
            || ($generation['expected_point_count'] ?? null) !== 100
            || ($generation['actual_point_count'] ?? null) !== 100
            || ($generation['point_manifest_digest'] ?? null) !== self::POINT_MANIFEST_DIGEST
            || ($embedding['profile_fingerprint'] ?? null) !== self::EMBEDDING_PROFILE_FINGERPRINT
            || ($sparse['profile_fingerprint'] ?? null) !== self::SPARSE_PROFILE_FINGERPRINT
        ) {
            throw new RuntimeException(self::RUN_ID.' materialised V3 lineage does not match the immutable reuse definition.');
        }
    }

    private function __construct() {}
}
