<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\Exp0008Definition;
use App\Support\Evaluation\Exp0008EngineeringBenchmark;
use App\Support\Evaluation\Exp0008EngineeringBenchmarkSource;
use RuntimeException;
use Tests\Concerns\UsesCurrentV3EngineeringFixture;
use Tests\TestCase;

final class Exp0008DefinitionTest extends TestCase
{
    use UsesCurrentV3EngineeringFixture;

    public function test_current_population_planner_retrieval_and_reused_generation_are_immutable(): void
    {
        $lineage = Exp0008Definition::lineage();

        $this->assertSame('EXP-0008-v3-final-engineering-confirmation', $lineage['id']);
        $this->assertSame(Exp0008EngineeringBenchmark::POPULATION_DIGEST, $lineage['engineering_population']['digest']);
        $this->assertSame(10, $lineage['engineering_population']['case_count']);
        $this->assertSame(31, $lineage['engineering_population']['variant_count']);
        $this->assertSame(Exp0008Definition::planner(), $lineage['planner']);
        $this->assertSame(5, $lineage['retrieval']['fusion']['rrf_k']);
        $this->assertSame(0.337890625, $lineage['retrieval']['evidence_threshold']);
        $this->assertSame(Exp0008Definition::MATERIALISED_POPULATION_DIGEST, $lineage['materialisation_reuse']['materialised_population_digest']);
        $this->assertSame(100, $lineage['active_generation']['expected_point_count']);
    }

    public function test_incomplete_or_drifted_reused_materialisation_fails_closed(): void
    {
        $state = $this->provisioning();
        Exp0008Definition::assertProvisioning($state);

        $state['generations']['hybrid_corpus']['point_manifest_digest'] = str_repeat('0', 64);
        $this->expectException(RuntimeException::class);
        Exp0008Definition::assertProvisioning($state);
    }

    public function test_current_v3_execution_source_contains_exact_population_and_expectations(): void
    {
        $source = app(Exp0008EngineeringBenchmarkSource::class)->load();
        $corpus = app(Exp0008EngineeringBenchmarkSource::class)->experimentCorpus();

        $this->assertSame(Exp0008EngineeringBenchmark::POPULATION_DIGEST, $source['manifest']['population_digest']);
        $this->assertSame(Exp0008EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST, $source['provisioning']['definition_digest']);
        $this->assertCount(10, $corpus['cases']);
        $this->assertSame(31, $corpus['variant_count']);
        $this->assertSame([], $source['independence']['overlap']['case_ids']);
        $this->assertSame([], $source['independence']['overlap']['semantic_cluster_ids']);
        $this->assertSame([], $source['independence']['overlap']['leakage_group_ids']);
        $this->assertFalse($source['independence']['held_out']['content_accessed']);
    }

    public function test_exp_0008_has_a_fresh_command_mode_and_checkpoint_identity(): void
    {
        $command = file_get_contents(app_path('Console/Commands/RunExp0008EngineeringExperimentCommand.php'));
        $runner = file_get_contents(app_path('Actions/Evaluation/RunEngineeringBenchmarkExperiment.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('evaluation:benchmark:run-exp-0008 {--repository-commit=}', $command);
        $this->assertStringNotContainsString('--dirty', $command);
        $this->assertIsString($runner);
        $this->assertStringContainsString("Exp0008Definition::RUN_ID, 'exp0008'", $runner);
        $this->assertStringContainsString('$this->exp0008Source->experimentCorpus()', $runner);
    }

    /** @return array<string, mixed> */
    private function provisioning(): array
    {
        return [
            'status' => 'MATERIALISED',
            'mapping_digest' => Exp0008Definition::PROVISIONING_MAPPING_DIGEST,
            'benchmark' => ['population_digest' => Exp0008Definition::MATERIALISED_POPULATION_DIGEST],
            'generations' => [
                'hybrid_corpus' => [
                    'public_id' => Exp0008Definition::ACTIVE_GENERATION_PUBLIC_ID,
                    'expected_point_count' => 100,
                    'actual_point_count' => 100,
                    'point_manifest_digest' => Exp0008Definition::POINT_MANIFEST_DIGEST,
                ],
                'embedding_space' => ['profile_fingerprint' => Exp0008Definition::EMBEDDING_PROFILE_FINGERPRINT],
                'sparse_space' => ['profile_fingerprint' => Exp0008Definition::SPARSE_PROFILE_FINGERPRINT],
            ],
        ];
    }
}
