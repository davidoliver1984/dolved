<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\CalExp0002Definition;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkSource;
use Tests\TestCase;

final class CalExp0002DefinitionTest extends TestCase
{
    public function test_definition_binds_the_compatible_v3_population_and_frozen_pipeline(): void
    {
        $lineage = CalExp0002Definition::lineage();

        $this->assertSame('CAL-EXP-0002-v3-evidence-threshold-calibration', $lineage['id']);
        $this->assertSame('3', $lineage['benchmark']['version']);
        $this->assertSame(44, $lineage['population']['case_count']);
        $this->assertSame(132, $lineage['population']['variant_count']);
        $this->assertSame(CalExp0002Definition::COMPATIBILITY_RESULT_DIGEST, $lineage['compatibility']['result_digest']);
        $this->assertSame(5, $lineage['candidate_pipeline']['rrf_k']);
        $this->assertSame(0.337890625, $lineage['candidate_pipeline']['factual_control_threshold']);
        $this->assertSame('single_pass_no_selective_retry', $lineage['provider_execution']);
    }

    public function test_command_has_no_split_or_dirty_override(): void
    {
        $command = file_get_contents(app_path('Console/Commands/RunCalExp0002Command.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('evaluation:benchmark:run-cal-exp-0002 {--repository-commit=}', $command);
        $this->assertStringNotContainsString('--split', $command);
        $this->assertStringNotContainsString('--dirty', $command);
    }

    public function test_isolated_population_and_compatibility_lineage_loads(): void
    {
        if (! is_file(EngineeringBenchmark::CAL_EXP_0002_POPULATION_MANIFEST)) {
            $this->markTestSkipped('The isolated CAL-EXP-0002 population is not mounted.');
        }
        $corpus = $this->app->make(EngineeringBenchmarkSource::class)->calExp0002Corpus();

        $this->assertSame('3', $corpus['benchmark']['version']);
        $this->assertSame(44, $corpus['case_count']);
        $this->assertSame(132, $corpus['variant_count']);
        $this->assertSame(CalExp0002Definition::CASE_IDS_DIGEST, $corpus['split']['case_ids_digest']);
        $this->assertCount(44, $corpus['split']['case_ids']);
    }
}
