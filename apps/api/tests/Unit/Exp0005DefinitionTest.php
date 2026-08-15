<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringRetrievalConfiguration;
use App\Support\Evaluation\Exp0005Definition;
use RuntimeException;
use Tests\TestCase;

final class Exp0005DefinitionTest extends TestCase
{
    public function test_identity_planner_population_and_retrieval_lineage_are_immutable(): void
    {
        $lineage = Exp0005Definition::lineage();

        $this->assertSame('EXP-0005-adr0022-v2-consolidated-engineering-baseline', $lineage['id']);
        $this->assertSame(Exp0005Definition::planner(), $lineage['planner']);
        $this->assertSame(42, $lineage['engineering_split']['case_count']);
        $this->assertSame(126, $lineage['engineering_split']['variant_count']);
        $this->assertSame(EngineeringBenchmark::ENGINEERING_CASE_IDS_DIGEST, $lineage['engineering_split']['case_ids_digest']);
        $this->assertSame(5, $lineage['retrieval']['fusion']['rrf_k']);
        $this->assertSame(0.337890625, $lineage['retrieval']['evidence_threshold']);
        $this->assertSame(5, $lineage['retrieval']['final_evidence_k']);
        $this->assertSame(EngineeringRetrievalConfiguration::RRF_K, $lineage['retrieval']['fusion']['rrf_k']);
        $this->assertSame(EngineeringRetrievalConfiguration::EVIDENCE_THRESHOLD, $lineage['retrieval']['evidence_threshold']);
    }

    public function test_frozen_engineering_policy_is_accepted_and_drift_fails_closed(): void
    {
        $policy = $this->policy();
        Exp0005Definition::assertPolicy($policy);
        $this->addToAssertionCount(1);

        $policy->fingerprint = str_repeat('0', 64);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires the frozen engineering policy');
        Exp0005Definition::assertPolicy($policy);
    }

    public function test_provisioning_lineage_is_accepted_and_drift_fails_closed(): void
    {
        $state = $this->state();
        Exp0005Definition::assertProvisioning($state);
        $this->addToAssertionCount(1);

        $state['generations']['hybrid_corpus']['expected_point_count'] = 98;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provisioning lineage does not match');
        Exp0005Definition::assertProvisioning($state);
    }

    public function test_command_has_no_dirty_override_and_runner_uses_engineering_population(): void
    {
        $command = file_get_contents(app_path('Console/Commands/RunExp0005EngineeringExperimentCommand.php'));
        $runner = file_get_contents(app_path('Actions/Evaluation/RunEngineeringBenchmarkExperiment.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('evaluation:benchmark:run-exp-0005 {--repository-commit=}', $command);
        $this->assertStringNotContainsString('--dirty', $command);
        $this->assertIsString($runner);
        $this->assertStringContainsString("Exp0005Definition::RUN_ID, 'exp0005'", $runner);
        $this->assertStringContainsString('$this->source->engineeringCorpus()', $runner);
    }

    private function policy(): EvidenceThresholdPolicy
    {
        return new EvidenceThresholdPolicy([
            'version' => EngineeringRetrievalConfiguration::VERSION,
            'fingerprint' => Exp0005Definition::POLICY_FINGERPRINT,
            'status' => EvidenceThresholdPolicyStatus::Calibrating,
        ]);
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return [
            'mapping_digest' => Exp0005Definition::PROVISIONING_MAPPING_DIGEST,
            'generations' => [
                'hybrid_corpus' => [
                    'public_id' => Exp0005Definition::ACTIVE_GENERATION_PUBLIC_ID,
                    'expected_point_count' => 99,
                    'point_manifest_digest' => Exp0005Definition::POINT_MANIFEST_DIGEST,
                ],
                'embedding_space' => [
                    'profile_fingerprint' => Exp0005Definition::EMBEDDING_PROFILE_FINGERPRINT,
                ],
                'sparse_space' => [
                    'profile_fingerprint' => Exp0005Definition::SPARSE_PROFILE_FINGERPRINT,
                ],
            ],
        ];
    }
}
