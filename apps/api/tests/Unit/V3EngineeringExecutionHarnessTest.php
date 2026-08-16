<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkSource;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class V3EngineeringExecutionHarnessTest extends TestCase
{
    public function test_definition_does_not_claim_materialisation(): void
    {
        $source = app(V3EngineeringBenchmarkSource::class)->load();

        $this->assertSame(V3EngineeringBenchmark::POPULATION_DIGEST, $source['manifest']['population_digest']);
        $this->assertSame(10, $source['manifest']['case_count']);
        $this->assertSame(31, $source['manifest']['variant_count']);
        $this->assertSame(V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST, $source['provisioning']['definition_digest']);
        $this->assertSame('DEFINITION_ONLY', $source['provisioning']['status']);
        $this->assertFalse($source['provisioning']['provider_calls_performed']);
        $this->assertCount(72, $source['provisioning']['document_families']);
        $this->assertCount(94, $source['provisioning']['documents']);
        $this->assertNull($source['provisioning']['canonical_chunks']['expected_count']);
        $this->assertNull($source['provisioning']['vector_projection']['expected_point_count']);
    }

    public function test_exact_v3_source_bytes_are_validated(): void
    {
        $source = app(V3EngineeringBenchmarkSource::class);
        $document = $source->load()['provisioning']['documents'][0];
        $contents = $source->document($document['source_path'], $document['source_sha256']);

        $this->assertSame($document['source_sha256'], hash('sha256', $contents));
    }

    public function test_v2_and_v3_identities_and_state_paths_are_separate(): void
    {
        $this->assertSame('v2', EngineeringBenchmark::VERSION);
        $this->assertSame('3', V3EngineeringBenchmark::VERSION);
        $this->assertNotSame(EngineeringBenchmark::WORKSPACE_SLUG, V3EngineeringBenchmark::WORKSPACE_SLUG);
        $this->assertNotSame(EngineeringBenchmark::STATE_PATH, V3EngineeringBenchmark::STATE_PATH);
    }

    public function test_incomplete_v3_state_cannot_claim_materialisation(): void
    {
        Storage::fake('local');
        $states = app(V3EngineeringBenchmarkState::class);
        $state = $states->write([
            'schema_version' => 'v1',
            'status' => 'PROVISIONING',
            'benchmark' => ['population_digest' => V3EngineeringBenchmark::POPULATION_DIGEST],
            'provisioning_definition_digest' => V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST,
        ]);

        $this->assertSame('PROVISIONING', $state['status']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $state['mapping_digest']);
        $this->assertEquals($state, $states->read());
    }

    public function test_authoritative_aliases_and_hierarchy_load_from_v3(): void
    {
        $locations = collect(app(V3EngineeringBenchmarkSource::class)->load()['provisioning']['locations'])->keyBy('location_id');

        $this->assertContains('Coventry', $locations['location.willow-bank']['aliases']);
        $this->assertSame('location.region.midlands', $locations['location.willow-bank']['parent_location_id']);
        $this->assertContains('Midlands', $locations['location.region.midlands']['aliases']);
        $this->assertContains('South West', $locations['location.region.south-west']['aliases']);
    }

    public function test_no_v3_reset_command_can_target_historical_v2_state(): void
    {
        $commands = Artisan::all();

        $this->assertArrayNotHasKey('evaluation:v3-engineering:reset', $commands);
        $this->assertArrayHasKey('evaluation:v3-engineering:provision', $commands);
        $this->assertArrayHasKey('evaluation:v3-engineering:verify-ingestion', $commands);
        $this->assertArrayHasKey('evaluation:v3-engineering:build-hybrid', $commands);
    }
}
