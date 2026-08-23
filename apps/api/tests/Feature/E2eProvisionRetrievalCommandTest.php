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
            'e2e.deterministic_retrieval_profile_path' => dirname(__DIR__, 4).'/contracts/testing/deterministic-retrieval-profile-v1.json',
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

        $dense = EmbeddingProfile::query()->where('provider', 'deterministic')->sole();
        $this->assertSame('token-hash-unit-vector-v3', $dense->model);
        $this->assertSame('deterministic-v3', $dense->adapter_version);
        $this->assertSame(1024, $dense->dimensions);
        $this->assertSame('d5a56dffa5539ac2c7b1582fcdcc0658855399532e9484868fd2f7c97e1b8218', $dense->fingerprint);

        $space = EmbeddingSpaceGeneration::query()->sole();
        $this->assertSame('dense', $space->vector_name);
        $this->assertSame(1024, $space->dimensions);
        $this->assertSame('cosine', $space->distance);

        $sparse = SparseEmbeddingProfile::query()->where('provider', 'deterministic')->sole();
        $this->assertSame('token-hash-sparse-v4', $sparse->model);
        $this->assertSame('lowercase-alphanumeric-v1', $sparse->tokenizer);
        $this->assertSame('deterministic-v4', $sparse->adapter_version);
        $this->assertSame('d4f361438791330c05d1e8125fc4f16df00280de1db9cbb8f8fb0325c756b9d7', $sparse->fingerprint);
    }

    public function test_it_refuses_an_ambiguous_resource_or_database_identity(): void
    {
        config(['e2e.resource_marker' => 'local']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ambiguous resource/database identity');
        Artisan::call('e2e:provision-retrieval');
    }

    public function test_it_rejects_a_stale_profile_under_the_v3_fingerprint(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4).'/contracts/testing/deterministic-retrieval-profile-v1.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $fixture['dense']['profile']['model'] = 'sha256-unit-vector-v1';
        $temporary = tempnam(sys_get_temp_dir(), 'dolved-e2e-profile-');
        if ($temporary === false) {
            $this->fail('Unable to create the temporary profile fixture.');
        }
        file_put_contents($temporary, json_encode($fixture, JSON_THROW_ON_ERROR));
        config(['e2e.deterministic_retrieval_profile_path' => $temporary]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('dense profile fingerprint is invalid');
            Artisan::call('e2e:provision-retrieval');
        } finally {
            unlink($temporary);
        }
    }
}
