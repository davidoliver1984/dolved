<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkSource;
use Tests\TestCase;

final class EngineeringBenchmarkSourceTest extends TestCase
{
    public function test_engineering_snapshot_is_digest_bound_and_contains_only_the_fixed_engineering_population(): void
    {
        $snapshot = app(EngineeringBenchmarkSource::class)->engineeringCorpus();
        $caseIds = collect($snapshot['cases'])->pluck('case_id')->all();
        $variantCount = collect($snapshot['cases'])->sum(
            fn (array $case): int => count($case['variants']),
        );

        $this->assertSame(EngineeringBenchmark::DIGEST, $snapshot['benchmark']['digest']);
        $this->assertSame('engineering_tuning', $snapshot['split']['name']);
        $this->assertSame(EngineeringBenchmark::EXPECTED_ENGINEERING_CASES, count($caseIds));
        $this->assertSame(EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS, $variantCount);
        $this->assertSame($snapshot['split']['case_ids'], $caseIds);
        $this->assertArrayNotHasKey('assignments', $snapshot['split']);
        $this->assertArrayNotHasKey('threshold_calibration', $snapshot['split']);
        $this->assertArrayNotHasKey('held_out_acceptance', $snapshot['split']);
    }

    public function test_exp_0002_runner_loads_only_the_engineering_snapshot(): void
    {
        $runner = file_get_contents(app_path('Actions/Evaluation/RunEngineeringBenchmarkExperiment.php'));

        $this->assertIsString($runner);
        $this->assertStringContainsString('$this->source->engineeringCorpus()', $runner);
        $this->assertStringNotContainsString('$this->source->compiledCorpus()', $runner);
        $this->assertStringNotContainsString('$this->source->load()', $runner);
    }
}
