<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\Exp0008EngineeringBenchmark;
use App\Support\Evaluation\Exp0008EngineeringBenchmarkSource;
use Tests\Concerns\UsesCurrentV3EngineeringFixture;
use Tests\TestCase;

final class V3EngineeringBenchmarkSourceTest extends TestCase
{
    use UsesCurrentV3EngineeringFixture;

    public function test_provider_free_population_and_provisioning_lineage_load_fail_closed(): void
    {
        $source = app(Exp0008EngineeringBenchmarkSource::class)->load();

        $this->assertCount(Exp0008EngineeringBenchmark::EXPECTED_CASES, $source['corpus']['cases']);
        $this->assertCount(Exp0008EngineeringBenchmark::EXPECTED_VARIANTS, $source['expectations']['expectations']);
        $this->assertSame(Exp0008EngineeringBenchmark::POPULATION_DIGEST, $source['manifest']['population_digest']);
        $this->assertSame(Exp0008EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST, $source['provisioning']['definition_digest']);
        $this->assertSame('DEFINITION_ONLY', $source['provisioning']['status']);
        $this->assertFalse($source['provisioning']['provider_calls_performed']);
        $this->assertNull($source['provisioning']['canonical_chunks']['expected_count']);
        $this->assertNull($source['provisioning']['vector_projection']['expected_point_count']);
        $this->assertCount(72, $source['provisioning']['document_families']);
        $this->assertCount(94, $source['provisioning']['documents']);
    }

    public function test_independence_evidence_has_no_calibration_overlap(): void
    {
        $source = app(Exp0008EngineeringBenchmarkSource::class)->load();

        $this->assertSame([], $source['independence']['overlap']['case_ids']);
        $this->assertSame([], $source['independence']['overlap']['semantic_cluster_ids']);
        $this->assertSame([], $source['independence']['overlap']['leakage_group_ids']);
        $this->assertSame('UNASSIGNED_AND_UNAVAILABLE', $source['independence']['held_out']['assignment_status']);
        $this->assertFalse($source['independence']['held_out']['content_accessed']);
    }
}
