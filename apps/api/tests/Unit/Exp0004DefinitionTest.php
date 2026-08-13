<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use App\Support\Evaluation\BenchmarkCanonicalJson;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkSource;
use App\Support\Evaluation\EngineeringBenchmarkState;
use App\Support\Evaluation\Exp0004Definition;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class Exp0004DefinitionTest extends TestCase
{
    public function test_exp_0004_identity_and_sole_variable_are_immutable(): void
    {
        $control = $this->controlPolicy();
        $treatment = Exp0004Definition::treatmentPolicy(
            $control,
            app(BenchmarkCanonicalJson::class),
        );
        $lineage = Exp0004Definition::lineage();

        $this->assertSame('EXP-0004-rrf-k-5-controlled-engineering-experiment', $lineage['id']);
        $this->assertSame('EXP-0003-post-reliability-corrected-engineering-baseline', $lineage['control_experiment_id']);
        $this->assertSame(
            ['name' => 'rrf_k', 'control' => 60, 'treatment' => 5],
            $lineage['sole_retrieval_variable'],
        );
        $this->assertSame(5, $treatment->rrf_k);
        $this->assertSame(EvidenceThresholdPolicyStatus::Calibrating, $treatment->status);
        $this->assertFalse($treatment->exists);

        $frozen = [
            'reranker_provider',
            'reranker_model',
            'reranker_adapter_version',
            'embedding_profile_fingerprint',
            'sparse_profile_fingerprint',
            'fusion_strategy',
            'fusion_version',
            'dense_candidate_k',
            'sparse_candidate_k',
            'fusion_candidate_k',
            'reranker_candidate_k',
            'evidence_threshold',
            'final_evidence_k',
            'calibration_corpus_version',
            'calibration_corpus_digest',
        ];
        foreach ($frozen as $field) {
            $this->assertSame($control->getAttribute($field), $treatment->getAttribute($field), $field);
        }
    }

    public function test_exp_0004_fails_closed_when_the_control_configuration_drifts(): void
    {
        $control = $this->controlPolicy();
        $control->fusion_candidate_k = 14;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('control policy differs at fusion_candidate_k');
        Exp0004Definition::treatmentPolicy($control, app(BenchmarkCanonicalJson::class));
    }

    public function test_exp_0004_uses_only_the_engineering_snapshot_and_isolated_output_root(): void
    {
        $snapshot = app(EngineeringBenchmarkSource::class)->engineeringCorpus();
        $runner = file_get_contents(app_path('Actions/Evaluation/RunEngineeringBenchmarkExperiment.php'));

        $this->assertSame(42, $snapshot['case_count']);
        $this->assertSame(126, $snapshot['variant_count']);
        $this->assertArrayNotHasKey('threshold_calibration', $snapshot['split']);
        $this->assertArrayNotHasKey('held_out_acceptance', $snapshot['split']);
        $this->assertSame('/evaluation-runs', config('evaluation.runs_root'));
        $this->assertIsString($runner);
        $this->assertStringContainsString('$this->source->engineeringCorpus()', $runner);
        $this->assertStringNotContainsString('$this->source->compiledCorpus()', $runner);
    }

    public function test_exp_0003_command_remains_unchanged_and_exp_0004_command_has_no_dirty_override(): void
    {
        $exp0003 = file_get_contents(app_path('Console/Commands/RunEngineeringBenchmarkExperimentCommand.php'));
        $exp0004 = file_get_contents(app_path('Console/Commands/RunExp0004EngineeringExperimentCommand.php'));

        $this->assertIsString($exp0003);
        $this->assertStringContainsString('evaluation:benchmark:run-exp-0003 {--repository-commit=} {--dirty=0}', $exp0003);
        $this->assertIsString($exp0004);
        $this->assertStringContainsString('evaluation:benchmark:run-exp-0004 {--repository-commit=}', $exp0004);
        $this->assertStringNotContainsString('--dirty', $exp0004);
    }

    public function test_provisioning_record_digest_mismatch_fails_closed(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(EngineeringBenchmark::STATE_PATH, json_encode([
            'schema_version' => 'v2',
            'mapping_digest' => str_repeat('0', 64),
        ], JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('digest does not match');
        app(EngineeringBenchmarkState::class)->read();
    }

    private function controlPolicy(): EvidenceThresholdPolicy
    {
        $policy = new EvidenceThresholdPolicy([
            'version' => 'exp-0001-dolved-care-v2-observational',
            'fingerprint' => Exp0004Definition::CONTROL_POLICY_FINGERPRINT,
            'status' => EvidenceThresholdPolicyStatus::Calibrating,
            'reranker_provider' => 'voyage',
            'reranker_model' => 'rerank-2.5',
            'reranker_adapter_version' => '1',
            'embedding_profile_fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            'sparse_profile_fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
            'fusion_strategy' => 'rrf',
            'fusion_version' => '1',
            'rrf_k' => 60,
            'dense_candidate_k' => 40,
            'sparse_candidate_k' => 40,
            'fusion_candidate_k' => 15,
            'reranker_candidate_k' => 15,
            'evidence_threshold' => 0.337890625,
            'final_evidence_k' => 5,
            'calibration_corpus_version' => 'v2-foundation-experimental',
            'calibration_corpus_digest' => EngineeringBenchmark::DIGEST,
        ]);
        $policy->public_id = '7eb0f029-0c9b-451c-a6c0-0ee309843a80';

        return $policy;
    }
}
