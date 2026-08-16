<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkSource;
use Tests\TestCase;

final class V3EngineeringBenchmarkSourceTest extends TestCase
{
    public function test_provider_free_population_and_provisioning_lineage_load_fail_closed(): void
    {
        $source = app(V3EngineeringBenchmarkSource::class)->load();

        $this->assertCount(V3EngineeringBenchmark::EXPECTED_CASES, $source['corpus']['cases']);
        $this->assertCount(V3EngineeringBenchmark::EXPECTED_VARIANTS, $source['expectations']['expectations']);
        $this->assertSame(V3EngineeringBenchmark::POPULATION_DIGEST, $source['manifest']['population_digest']);
        $this->assertSame(V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST, $source['provisioning']['definition_digest']);
        $this->assertSame('DEFINITION_ONLY', $source['provisioning']['status']);
        $this->assertFalse($source['provisioning']['provider_calls_performed']);
        $this->assertNull($source['provisioning']['canonical_chunks']['expected_count']);
        $this->assertNull($source['provisioning']['vector_projection']['expected_point_count']);
        $this->assertCount(V3EngineeringBenchmark::EXPECTED_FAMILIES, $source['provisioning']['document_families']);
        $this->assertCount(V3EngineeringBenchmark::EXPECTED_VERSIONS, $source['provisioning']['documents']);
    }

    public function test_independence_evidence_has_no_calibration_overlap(): void
    {
        $source = app(V3EngineeringBenchmarkSource::class)->load();

        $this->assertSame([], $source['independence']['overlap']['case_ids']);
        $this->assertSame([], $source['independence']['overlap']['semantic_cluster_ids']);
        $this->assertSame([], $source['independence']['overlap']['leakage_group_ids']);
        $this->assertSame('UNASSIGNED_AND_UNAVAILABLE', $source['independence']['held_out']['assignment_status']);
        $this->assertFalse($source['independence']['held_out']['content_accessed']);
    }
}
