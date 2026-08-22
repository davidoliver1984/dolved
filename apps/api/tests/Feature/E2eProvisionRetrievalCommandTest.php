<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class E2eProvisionRetrievalCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn (): string => 'e2e');
        config([
            'app.env' => 'e2e',
            'e2e.resource_marker' => 'dolved-e2e',
            'e2e.database_marker' => ':memory:',
        ]);
    }

    public function test_it_provisions_the_deterministic_dense_and_sparse_profile_idempotently(): void
    {
        $this->assertSame(0, Artisan::call('e2e:provision-retrieval'));
        $this->assertSame(0, Artisan::call('e2e:provision-retrieval'));

        $this->assertSame(1, EmbeddingProfile::query()->where('provider', 'deterministic')->count());
        $this->assertSame(1, EmbeddingSpaceGeneration::query()->where('collection_name', 'dolved-e2e-vectors-v1')->count());
        $this->assertSame(1, SparseEmbeddingProfile::query()->where('provider', 'deterministic')->count());
        $this->assertSame(1, SparseSpaceGeneration::query()->count());
    }

    public function test_it_refuses_an_ambiguous_resource_or_database_identity(): void
    {
        config(['e2e.resource_marker' => 'local']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ambiguous resource/database identity');
        Artisan::call('e2e:provision-retrieval');
    }
}
