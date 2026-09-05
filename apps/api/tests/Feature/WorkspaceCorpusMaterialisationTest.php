<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Retrieval\MaterialiseWorkspaceCorpusGeneration;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\RetrievalException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseSpaceGeneration;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusMaterialisationBatch;
use App\Services\Ingestion\DeterministicVectorPointIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceCorpusMaterialisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_materialisation_batches_records_and_atomically_activates_one_workspace(): void
    {
        [$workspace, $source, $dense, $sparse] = $this->fixture(5);
        $this->fakeSuccess();

        $target = app(MaterialiseWorkspaceCorpusGeneration::class)->handle($workspace, $dense, $sparse, 2);

        $this->assertSame([2, 2, 1], $target->materialisationBatches->pluck('input_count')->all());
        $this->assertSame(['completed', 'completed', 'completed'], $target->materialisationBatches->pluck('status')->all());
        $this->assertSame(5, $target->expected_point_count);
        $this->assertSame($target->id, $workspace->fresh()->active_workspace_corpus_generation_id);
        $this->assertSame(WorkspaceCorpusGenerationStatus::Superseded, $source->fresh()->status);
        $this->assertSame(WorkspaceCorpusGenerationStatus::Active, $target->status);
        $recorded = collect(Http::recorded())->map(fn (array $entry): array => [
            'kind' => str_ends_with($entry[0]->url(), '/corpus/rebuild-batch') ? 'batch' : 'verify',
            'workspace_id' => $entry[0]->data()['workspace_id'],
        ])->all();
        $this->assertCount(3, array_filter($recorded, fn (array $request): bool => $request['kind'] === 'batch'));
        $this->assertSame([$workspace->public_id], array_values(array_unique(array_column($recorded, 'workspace_id'))));
    }

    public function test_completed_batches_are_skipped_after_middle_batch_failure(): void
    {
        [$workspace, $source, $dense, $sparse] = $this->fixture(5);
        $batchCalls = 0;
        Http::fake(function (Request $request) use (&$batchCalls) {
            $payload = $request->data();
            if (str_ends_with($request->url(), '/corpus/rebuild-batch')) {
                $batchCalls++;
                if ($batchCalls === 2) {
                    return Http::response(['detail' => ['category' => 'invalid_input']], 422);
                }
            }

            return $this->successfulResponse($request);
        });

        try {
            app(MaterialiseWorkspaceCorpusGeneration::class)->handle($workspace, $dense, $sparse, 2);
            $this->fail('The middle batch failure was not propagated.');
        } catch (RetrievalException) {
            $this->assertSame(WorkspaceCorpusGenerationStatus::Active, $source->fresh()->status);
            $this->assertSame(['completed', 'pending'], WorkspaceCorpusMaterialisationBatch::query()
                ->orderBy('batch_number')->pluck('status')->all());
        }

        $resumedBatchSizes = [];
        Http::fake(function (Request $request) use (&$resumedBatchSizes) {
            if (str_ends_with($request->url(), '/corpus/rebuild-batch')) {
                $resumedBatchSizes[] = count($request->data()['chunks']);
            }

            return $this->successfulResponse($request);
        });
        $target = app(MaterialiseWorkspaceCorpusGeneration::class)->handle($workspace->fresh(), $dense, $sparse, 2);

        $this->assertSame([2, 1], $resumedBatchSizes);
        $this->assertSame(5, $target->expected_point_count);
        $this->assertSame(3, $target->materialisationBatches()->where('status', 'completed')->count());
    }

    public function test_incomplete_or_reordered_results_never_activate_target(): void
    {
        [$workspace, $source, $dense, $sparse] = $this->fixture(3);
        Http::fake(function (Request $request) {
            $body = $this->successfulResponseBody($request);
            if (str_ends_with($request->url(), '/corpus/rebuild-batch')) {
                $body['point_ids'] = array_reverse($body['point_ids']);
            }

            return Http::response($body);
        });

        try {
            app(MaterialiseWorkspaceCorpusGeneration::class)->handle($workspace, $dense, $sparse, 3);
            $this->fail('Reordered point identities were accepted.');
        } catch (RetrievalException) {
            $this->assertSame($source->id, $workspace->fresh()->active_workspace_corpus_generation_id);
            $this->assertSame(WorkspaceCorpusGenerationStatus::Active, $source->fresh()->status);
            $this->assertDatabaseMissing('workspace_corpus_generations', [
                'workspace_id' => $workspace->id,
                'status' => WorkspaceCorpusGenerationStatus::Active->value,
                'embedding_space_generation_id' => $dense->id,
            ]);
        }
    }

    public function test_wrong_workspace_and_incompatible_model_lineage_fail_closed(): void
    {
        [$workspace, , $dense, $sparse] = $this->fixture(2);
        $other = Workspace::factory()->create();
        $other->setRelation('activeCorpusGeneration', $workspace->activeCorpusGeneration);

        try {
            app(MaterialiseWorkspaceCorpusGeneration::class)->handle($other, $dense, $sparse);
            $this->fail('A cross-workspace source generation was accepted.');
        } catch (RetrievalException) {
            $this->assertDatabaseCount('workspace_corpus_materialisation_batches', 0);
        }

        $wrongDense = EmbeddingSpaceGeneration::factory()->available()->create();
        $this->expectException(RetrievalException::class);
        app(MaterialiseWorkspaceCorpusGeneration::class)->handle($workspace, $wrongDense, $sparse);
    }

    public function test_provider_profile_rejection_and_activation_race_preserve_source(): void
    {
        [$workspace, $source, $dense, $sparse] = $this->fixture(2);
        Http::fake(fn () => Http::response(['detail' => ['category' => 'profile_lineage_mismatch']], 422));
        try {
            app(MaterialiseWorkspaceCorpusGeneration::class)->handle($workspace, $dense, $sparse);
            $this->fail('Provider profile rejection was not propagated.');
        } catch (RetrievalException) {
            $this->assertSame(WorkspaceCorpusGenerationStatus::Active, $source->fresh()->status);
        }

        Http::fake(function (Request $request) use ($source) {
            if (str_ends_with($request->url(), '/corpus/verify')) {
                $source->fresh()->forceFill([
                    'status' => WorkspaceCorpusGenerationStatus::Superseded,
                    'superseded_at' => now(),
                ])->save();
            }

            return $this->successfulResponse($request);
        });
        $this->expectException(RetrievalException::class);
        app(MaterialiseWorkspaceCorpusGeneration::class)->handle($workspace->fresh(), $dense, $sparse);
    }

    public function test_explicit_production_profiles_can_be_provisioned_in_isolated_runtime(): void
    {
        $this->artisan('ingestion:provision-embedding-space', ['--production-profile' => true])->assertSuccessful();
        $dense = EmbeddingSpaceGeneration::query()->where('collection_name', 'rag-platform-vectors-v1')->sole();
        $this->assertSame('voyage', $dense->embeddingProfile->provider);
        $this->assertSame('voyage-4-large', $dense->embeddingProfile->model);

        $this->artisan('retrieval:provision-sparse-space', [
            'embedding-space' => $dense->public_id,
            '--production-profile' => true,
        ])->assertSuccessful();
        $sparse = SparseSpaceGeneration::query()->where('embedding_space_generation_id', $dense->id)->sole();
        $this->assertSame('fastembed', $sparse->sparseEmbeddingProfile->provider);
        $this->assertSame('prithivida/Splade_PP_en_v1', $sparse->sparseEmbeddingProfile->model);
    }

    /** @return array{Workspace, WorkspaceCorpusGeneration, EmbeddingSpaceGeneration, SparseSpaceGeneration} */
    private function fixture(int $chunkCount): array
    {
        $workspace = Workspace::factory()->create();
        $source = WorkspaceCorpusGeneration::factory()->active()->create(['workspace_id' => $workspace->id]);
        $workspace->forceFill(['active_workspace_corpus_generation_id' => $source->id])->save();
        foreach (range(1, $chunkCount) as $ordinal) {
            $document = Document::factory()->indexed()->create(['workspace_id' => $workspace->id]);
            $chunk = DocumentChunk::factory()->create([
                'workspace_id' => $workspace->id,
                'document_id' => $document->id,
                'ordinal' => $ordinal,
            ]);
            $source->documentChunks()->attach($chunk->id, [
                'workspace_id' => $workspace->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $profile = EmbeddingProfile::factory()->voyageV1()->create();
        $dense = EmbeddingSpaceGeneration::factory()->for($profile)->available()->create([
            'dimensions' => 1024,
            'collection_name' => 'rag-platform-vectors-v1',
        ]);
        $sparse = SparseSpaceGeneration::factory()->available()->create([
            'embedding_space_generation_id' => $dense->id,
        ]);

        return [$workspace, $source, $dense, $sparse];
    }

    private function fakeSuccess(): void
    {
        Http::fake(fn (Request $request) => $this->successfulResponse($request));
    }

    private function successfulResponse(Request $request): mixed
    {
        return Http::response($this->successfulResponseBody($request));
    }

    /** @return array<string, mixed> */
    private function successfulResponseBody(Request $request): array
    {
        $payload = $request->data();
        $items = $payload['chunks'] ?? $payload['points'];
        $identity = app(DeterministicVectorPointIdentity::class);
        $pointIds = collect($items)->map(fn (array $item): string => $identity->forChunk(
            $payload['vector_space']['embedding_space_generation_id'],
            $payload['workspace_id'],
            $payload['workspace_corpus_generation_id'],
            $item['chunk_id'],
        ))->values()->all();

        return [
            'contract_version' => 1,
            'request_id' => $payload['request_id'],
            'complete' => true,
            'point_ids' => $pointIds,
        ];
    }
}
