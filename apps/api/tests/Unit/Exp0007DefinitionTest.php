<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\Exp0007Definition;
use App\Support\Evaluation\V3EngineeringBenchmark;
use RuntimeException;
use Tests\TestCase;

final class Exp0007DefinitionTest extends TestCase
{
    public function test_identity_population_planner_retrieval_and_generation_are_immutable(): void
    {
        $lineage = Exp0007Definition::lineage();

        $this->assertSame('EXP-0007-v3-engineering-regression-confirmation', $lineage['id']);
        $this->assertSame(V3EngineeringBenchmark::POPULATION_DIGEST, $lineage['engineering_population']['digest']);
        $this->assertSame(10, $lineage['engineering_population']['case_count']);
        $this->assertSame(31, $lineage['engineering_population']['variant_count']);
        $this->assertSame(Exp0007Definition::planner(), $lineage['planner']);
        $this->assertSame(5, $lineage['retrieval']['fusion']['rrf_k']);
        $this->assertSame(0.337890625, $lineage['retrieval']['evidence_threshold']);
        $this->assertSame(100, $lineage['provisioning']['expected_point_count']);
    }

    public function test_incomplete_or_drifted_materialisation_fails_closed(): void
    {
        $state = $this->provisioning();
        Exp0007Definition::assertProvisioning($state);

        $state['status'] = 'DENSE_VERIFIED';
        $this->expectException(RuntimeException::class);
        Exp0007Definition::assertProvisioning($state);
    }

    /** @return array<string, mixed> */
    private function provisioning(): array
    {
        return [
            'status' => 'MATERIALISED',
            'mapping_digest' => Exp0007Definition::PROVISIONING_MAPPING_DIGEST,
            'benchmark' => ['population_digest' => V3EngineeringBenchmark::POPULATION_DIGEST],
            'generations' => [
                'hybrid_corpus' => [
                    'public_id' => Exp0007Definition::ACTIVE_GENERATION_PUBLIC_ID,
                    'expected_point_count' => 100,
                    'actual_point_count' => 100,
                    'point_manifest_digest' => Exp0007Definition::POINT_MANIFEST_DIGEST,
                ],
                'embedding_space' => ['profile_fingerprint' => Exp0007Definition::EMBEDDING_PROFILE_FINGERPRINT],
                'sparse_space' => ['profile_fingerprint' => Exp0007Definition::SPARSE_PROFILE_FINGERPRINT],
            ],
        ];
    }
}
