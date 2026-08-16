<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Evaluation\BuildV3EngineeringHybridCorpus;
use App\Actions\Evaluation\ProvisionV3EngineeringBenchmark;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\OrganisationalLocation;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkSource;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class V3EngineeringProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_definition_is_materialised_through_existing_document_and_ingestion_actions(): void
    {
        Storage::fake('local');
        Storage::fake((string) config('documents.storage_disk'));
        $commit = str_repeat('a', 40);

        $state = app(ProvisionV3EngineeringBenchmark::class)->handle($commit);
        $definition = app(V3EngineeringBenchmarkSource::class)->load()['provisioning'];

        $this->assertSame('QUEUED', $state['status']);
        $this->assertSame($commit, $state['repository_commit']);
        $this->assertSame(V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST, $state['provisioning_definition_digest']);
        $this->assertCount(72, $state['document_families']);
        $this->assertCount(94, $state['document_versions']);
        $this->assertCount(94, $state['ingestion_events']);
        $this->assertSame(72, DocumentFamily::query()->count());
        $this->assertSame(94, Document::query()->count());
        $this->assertDatabaseHas('workspaces', [
            'slug' => V3EngineeringBenchmark::WORKSPACE_SLUG,
            'public_id' => $definition['workspace']['public_id'],
        ]);
        $this->assertDatabaseMissing('workspaces', ['slug' => EngineeringBenchmark::WORKSPACE_SLUG]);
        $this->assertSame(10, OrganisationalLocation::query()->count());
        $willow = OrganisationalLocation::query()->where('name', 'Willow Bank Community Service')->sole();
        $midlands = OrganisationalLocation::query()->where('name', 'Midlands Region')->sole();
        $this->assertSame($midlands->id, $willow->parent_id);
        $this->assertTrue($willow->aliases()->where('alias', 'Coventry')->exists());
        $this->assertTrue($midlands->aliases()->where('alias', 'Midlands')->exists());
        $this->assertSame('QUEUED', app(V3EngineeringBenchmarkState::class)->read()['status']);
    }

    public function test_materialisation_fails_closed_before_dense_verification(): void
    {
        Storage::fake('local');
        app(V3EngineeringBenchmarkState::class)->write([
            'schema_version' => 'v1',
            'status' => 'QUEUED',
            'benchmark' => ['population_digest' => V3EngineeringBenchmark::POPULATION_DIGEST],
            'provisioning_definition_digest' => V3EngineeringBenchmark::PROVISIONING_DEFINITION_DIGEST,
            'workspace' => ['slug' => V3EngineeringBenchmark::WORKSPACE_SLUG],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only the verified V3 dense corpus can be rebuilt.');
        app(BuildV3EngineeringHybridCorpus::class)->handle();
    }
}
