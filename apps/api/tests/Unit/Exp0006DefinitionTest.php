<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringRetrievalConfiguration;
use App\Support\Evaluation\Exp0006Definition;
use RuntimeException;
use Tests\TestCase;

final class Exp0006DefinitionTest extends TestCase
{
    public function test_identity_planner_population_and_retrieval_lineage_are_immutable(): void
    {
        $lineage = Exp0006Definition::lineage();

        $this->assertSame('EXP-0006-adr0022-v4-consolidated-engineering-confirmation', $lineage['id']);
        $this->assertSame(Exp0006Definition::planner(), $lineage['planner']);
        $this->assertSame(42, $lineage['engineering_split']['case_count']);
        $this->assertSame(126, $lineage['engineering_split']['variant_count']);
        $this->assertSame(EngineeringBenchmark::ENGINEERING_CASE_IDS_DIGEST, $lineage['engineering_split']['case_ids_digest']);
        $this->assertSame(5, $lineage['retrieval']['fusion']['rrf_k']);
        $this->assertSame(0.337890625, $lineage['retrieval']['evidence_threshold']);
        $this->assertSame(5, $lineage['retrieval']['final_evidence_k']);
        $this->assertSame(EngineeringRetrievalConfiguration::RRF_K, $lineage['retrieval']['fusion']['rrf_k']);
        $this->assertSame(EngineeringRetrievalConfiguration::EVIDENCE_THRESHOLD, $lineage['retrieval']['evidence_threshold']);
    }

    public function test_frozen_policy_and_planner_lineage_fail_closed_on_drift(): void
    {
        $policy = $this->policy();
        Exp0006Definition::assertPolicy($policy);
        Exp0006Definition::assertPlanner(Exp0006Definition::planner());
        $this->addToAssertionCount(2);

        $planner = Exp0006Definition::planner();
        $planner['prompt_version'] = 'adr-0022-v3';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires the approved ADR-0022-v4 planner lineage');
        Exp0006Definition::assertPlanner($planner);
    }

    public function test_provisioning_lineage_is_accepted_and_drift_fails_closed(): void
    {
        $state = $this->state();
        Exp0006Definition::assertProvisioning($state);
        $this->addToAssertionCount(1);

        $state['generations']['hybrid_corpus']['expected_point_count'] = 98;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provisioning lineage does not match');
        Exp0006Definition::assertProvisioning($state);
    }

    public function test_command_has_no_dirty_override_and_runner_uses_engineering_population(): void
    {
        $command = file_get_contents(app_path('Console/Commands/RunExp0006EngineeringExperimentCommand.php'));
        $runner = file_get_contents(app_path('Actions/Evaluation/RunEngineeringBenchmarkExperiment.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('evaluation:benchmark:run-exp-0006 {--repository-commit=}', $command);
        $this->assertStringNotContainsString('--dirty', $command);
        $this->assertIsString($runner);
        $this->assertStringContainsString("Exp0006Definition::RUN_ID, 'exp0006'", $runner);
        $this->assertStringContainsString('$this->source->engineeringCorpus()', $runner);
    }

    private function policy(): EvidenceThresholdPolicy
    {
        return new EvidenceThresholdPolicy([
            'version' => EngineeringRetrievalConfiguration::VERSION,
            'fingerprint' => Exp0006Definition::POLICY_FINGERPRINT,
            'status' => EvidenceThresholdPolicyStatus::Calibrating,
        ]);
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return [
            'mapping_digest' => Exp0006Definition::PROVISIONING_MAPPING_DIGEST,
            'generations' => [
                'hybrid_corpus' => [
                    'public_id' => Exp0006Definition::ACTIVE_GENERATION_PUBLIC_ID,
                    'expected_point_count' => 99,
                    'point_manifest_digest' => Exp0006Definition::POINT_MANIFEST_DIGEST,
                ],
                'embedding_space' => [
                    'profile_fingerprint' => Exp0006Definition::EMBEDDING_PROFILE_FINGERPRINT,
                ],
                'sparse_space' => [
                    'profile_fingerprint' => Exp0006Definition::SPARSE_PROFILE_FINGERPRINT,
                ],
            ],
        ];
    }
}
