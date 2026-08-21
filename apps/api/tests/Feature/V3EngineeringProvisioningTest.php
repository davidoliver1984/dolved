<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Evaluation\BuildV3EngineeringHybridCorpus;
use App\Actions\Evaluation\ProvisionV3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\UsesCurrentV3EngineeringFixture;
use Tests\TestCase;

final class V3EngineeringProvisioningTest extends TestCase
{
    use RefreshDatabase;
    use UsesCurrentV3EngineeringFixture;

    public function test_superseded_historical_definition_cannot_be_reprovisioned(): void
    {
        Storage::fake('local');
        Storage::fake((string) config('documents.storage_disk'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Benchmark V3 engineering population or provisioning definition is invalid.');
        app(ProvisionV3EngineeringBenchmark::class)->handle(str_repeat('a', 40));
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
