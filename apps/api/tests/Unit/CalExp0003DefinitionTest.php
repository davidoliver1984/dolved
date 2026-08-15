<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\CalExp0002Definition;
use App\Support\Evaluation\CalExp0003Definition;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkSource;
use Tests\TestCase;

final class CalExp0003DefinitionTest extends TestCase
{
    public function test_definition_binds_unchanged_population_pipeline_and_hardened_planner(): void
    {
        $lineage = CalExp0003Definition::lineage();

        $this->assertSame('CAL-EXP-0003-v3-post-planner-hardening-calibration', $lineage['id']);
        $this->assertSame(CalExp0002Definition::RUN_ID, $lineage['predecessor']['id']);
        $this->assertSame('immutable_failed_closed', $lineage['predecessor']['disposition']);
        $this->assertSame(CalExp0002Definition::BENCHMARK_DIGEST, $lineage['benchmark']['digest']);
        $this->assertSame(CalExp0002Definition::POPULATION_DIGEST, $lineage['population']['digest']);
        $this->assertSame(CalExp0002Definition::COMPATIBILITY_RESULT_DIGEST, $lineage['compatibility']['result_digest']);
        $this->assertSame(44, $lineage['population']['case_count']);
        $this->assertSame(132, $lineage['population']['variant_count']);
        $this->assertSame('structured-chat-v3', $lineage['planner']['adapter_version']);
        $this->assertSame(CalExp0003Definition::PLANNER_FINGERPRINT, $lineage['planner']['fingerprint']);
        $this->assertSame(CalExp0002Definition::CONTROL_THRESHOLD, $lineage['candidate_pipeline']['factual_control_threshold']);
        $this->assertSame(CalExp0002Definition::lineage()['candidate_pipeline'], $lineage['candidate_pipeline']);
    }

    public function test_historical_definition_retains_its_approved_planner_fingerprint(): void
    {
        $planner = CalExp0003Definition::planner();
        $fingerprint = $planner['fingerprint'];
        unset($planner['fingerprint']);
        $fingerprintInput = $planner;
        ksort($fingerprintInput);
        $calculated = hash(
            'sha256',
            json_encode($fingerprintInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        $this->assertSame(CalExp0003Definition::PLANNER_FINGERPRINT, $fingerprint);
        $this->assertSame($fingerprint, $calculated);
    }

    public function test_command_has_no_split_or_dirty_override(): void
    {
        $command = file_get_contents(app_path('Console/Commands/RunCalExp0003Command.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('evaluation:benchmark:run-cal-exp-0003 {--repository-commit=}', $command);
        $this->assertStringNotContainsString('--split', $command);
        $this->assertStringNotContainsString('--dirty', $command);
    }

    public function test_isolated_population_and_compatibility_lineage_loads(): void
    {
        if (! is_file(EngineeringBenchmark::CAL_EXP_0002_POPULATION_MANIFEST)) {
            $this->markTestSkipped('The isolated CAL-EXP-0003 population is not mounted.');
        }
        $corpus = $this->app->make(EngineeringBenchmarkSource::class)->calExp0003Corpus();

        $this->assertSame('3', $corpus['benchmark']['version']);
        $this->assertSame(44, $corpus['case_count']);
        $this->assertSame(132, $corpus['variant_count']);
        $this->assertSame(CalExp0003Definition::CASE_IDS_DIGEST, $corpus['split']['case_ids_digest']);
        $this->assertCount(44, $corpus['split']['case_ids']);
    }
}
