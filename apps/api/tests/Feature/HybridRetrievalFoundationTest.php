<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Retrieval\RebuildHybridCorpusGeneration;
use App\Actions\Retrieval\RollbackWorkspaceCorpusGeneration;
use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\EvidenceThresholdPolicyStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\RetrievalException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\EvidenceThresholdPolicy;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Services\Ingestion\DeterministicVectorPointIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class HybridRetrievalFoundationTest extends TestCase
{
    use RefreshDatabase;

    private bool $forceIncompleteVerification = false;

    public function test_sparse_profiles_spaces_and_threshold_policies_preserve_lineage(): void
    {
        $profile = SparseEmbeddingProfile::factory()->spladeV1()->create();
        $space = SparseSpaceGeneration::factory()->available()->create([
            'sparse_embedding_profile_id' => $profile->id,
        ]);
        $policy = EvidenceThresholdPolicy::factory()->active()->create([
            'sparse_profile_fingerprint' => $profile->fingerprint,
            'embedding_profile_fingerprint' => $space->embeddingSpaceGeneration->embeddingProfile->fingerprint,
            'evidence_threshold' => 0.337890625,
        ]);

        $this->assertTrue($space->sparseEmbeddingProfile->is($profile));
        $this->assertTrue($space->embeddingSpaceGeneration->sparseSpaceGenerations->contains($space));
        $this->assertSame(EmbeddingSpaceGenerationStatus::Available, $space->status);
        $this->assertSame(EvidenceThresholdPolicyStatus::Active, $policy->status);
        $this->assertSame(0.337890625, $policy->fresh()->evidence_threshold);
    }

    public function test_provisional_document_aware_policy_is_idempotent_and_keeps_reviewed_numeric_values(): void
    {
        $arguments = [
            '--dense-fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            '--sparse-fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
        ];
        $this->assertSame(0, Artisan::call('retrieval:provision-evidence-threshold-policy', $arguments), Artisan::output());
        $this->assertSame(0, Artisan::call('retrieval:provision-evidence-threshold-policy', $arguments), Artisan::output());

        $this->assertDatabaseCount('evidence_threshold_policies', 1);
        $this->assertDatabaseHas('evidence_threshold_policies', [
            'fingerprint' => '8adb1a507bda52e7524903b77cc29dea7f602ad8f0d082c9e6421c07ed6227fb',
            'status' => EvidenceThresholdPolicyStatus::Active->value,
            'reranker_adapter_version' => 'document-metadata-v2',
            'evidence_threshold' => 0.337890625,
        ]);
        $this->artisan('retrieval:provision-evidence-threshold-policy', [
            '--dense-fingerprint' => str_repeat('0', 64),
            '--sparse-fingerprint' => $arguments['--sparse-fingerprint'],
        ])->assertFailed();
        $this->assertDatabaseCount('evidence_threshold_policies', 1);
    }

    public function test_provisional_policy_provisioning_rejects_incompatible_existing_identity(): void
    {
        EvidenceThresholdPolicy::factory()->active()->create([
            'version' => 'provisional-r28-query-evidence-contract-v1',
            'fingerprint' => hash('sha256', 'incompatible'),
            'embedding_profile_fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            'sparse_profile_fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
        ]);

        $this->artisan('retrieval:provision-evidence-threshold-policy', [
            '--dense-fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            '--sparse-fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
        ])->assertFailed();
        $this->assertDatabaseCount('evidence_threshold_policies', 1);
    }

    public function test_provisional_policy_retires_the_prior_compatible_active_identity(): void
    {
        EvidenceThresholdPolicy::factory()->active()->create([
            'version' => 'r28-production-path-diagnostic-v1',
            'fingerprint' => 'f85912b00320582401d9e8f1af0dec1957370fbec4b8b98eb1bb2820f3f4a521',
            'reranker_provider' => 'voyage',
            'reranker_model' => 'rerank-2.5',
            'reranker_adapter_version' => '1',
            'embedding_profile_fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            'sparse_profile_fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
            'fusion_strategy' => 'rrf',
            'fusion_version' => '1',
            'rrf_k' => 5,
            'dense_candidate_k' => 40,
            'sparse_candidate_k' => 40,
            'fusion_candidate_k' => 15,
            'reranker_candidate_k' => 15,
            'evidence_threshold' => 0.337890625,
            'final_evidence_k' => 5,
            'calibration_corpus_version' => 'v2-foundation-experimental',
            'calibration_corpus_digest' => 'aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d',
        ]);

        $exit = Artisan::call('retrieval:provision-evidence-threshold-policy', [
            '--dense-fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            '--sparse-fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertDatabaseCount('evidence_threshold_policies', 2);
        $this->assertDatabaseHas('evidence_threshold_policies', [
            'version' => 'r28-production-path-diagnostic-v1',
            'fingerprint' => 'f85912b00320582401d9e8f1af0dec1957370fbec4b8b98eb1bb2820f3f4a521',
            'status' => EvidenceThresholdPolicyStatus::Retired->value,
        ]);
        $this->assertDatabaseHas('evidence_threshold_policies', [
            'version' => 'provisional-r28-query-evidence-contract-v1',
            'fingerprint' => '8adb1a507bda52e7524903b77cc29dea7f602ad8f0d082c9e6421c07ed6227fb',
            'status' => EvidenceThresholdPolicyStatus::Active->value,
        ]);
    }

    public function test_hybrid_generation_requires_compatible_available_sparse_space_and_verification(): void
    {
        $workspace = Workspace::factory()->create();
        $dense = EmbeddingSpaceGeneration::factory()->available()->create();
        $sparse = SparseSpaceGeneration::factory()->available()->create([
            'embedding_space_generation_id' => $dense->id,
        ]);

        $this->expectException(LogicException::class);
        WorkspaceCorpusGeneration::factory()->active()->create([
            'workspace_id' => $workspace->id,
            'embedding_space_generation_id' => $dense->id,
            'sparse_space_generation_id' => $sparse->id,
            'expected_point_count' => null,
            'point_manifest_digest' => null,
            'verified_at' => null,
        ]);
    }

    public function test_policy_rejects_non_monotonic_candidate_configuration(): void
    {
        $this->expectException(LogicException::class);
        EvidenceThresholdPolicy::factory()->create([
            'fusion_candidate_k' => 10,
            'reranker_candidate_k' => 11,
        ]);
    }

    public function test_sparse_space_provisioning_is_explicit_idempotent_and_fingerprinted(): void
    {
        $embeddingSpace = EmbeddingSpaceGeneration::factory()->available()->create();

        $this->artisan('retrieval:provision-sparse-space', [
            'embedding-space' => $embeddingSpace->public_id,
        ])->assertSuccessful();
        $this->artisan('retrieval:provision-sparse-space', [
            'embedding-space' => $embeddingSpace->public_id,
        ])->assertSuccessful();

        $this->assertDatabaseCount('sparse_embedding_profiles', 1);
        $this->assertDatabaseCount('sparse_space_generations', 1);
        $this->assertDatabaseHas('sparse_embedding_profiles', [
            'fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
            'provider' => 'fastembed',
            'model' => 'prithivida/Splade_PP_en_v1',
            'model_revision' => 'efcd182bc7eb351e81a9445752d4388c2bab500b',
        ]);
    }

    public function test_hybrid_rebuild_is_complete_resumable_and_activates_atomically(): void
    {
        [$workspace, $source, $sparse] = $this->corpusFixture();
        $this->fakeCorpusService();

        $target = app(RebuildHybridCorpusGeneration::class)->handle($workspace, $sparse, 1);
        $same = app(RebuildHybridCorpusGeneration::class)->handle($workspace->fresh(), $sparse, 1);

        $this->assertSame($target->id, $same->id);
        $this->assertSame(WorkspaceCorpusGenerationStatus::Superseded, $source->fresh()->status);
        $this->assertSame(WorkspaceCorpusGenerationStatus::Active, $target->status);
        $this->assertSame($target->id, $workspace->fresh()->active_workspace_corpus_generation_id);
        $this->assertSame($source->id, $target->rebuilt_from_generation_id);
        $this->assertSame($source->documentChunks()->count(), $target->documentChunks()->count());
        $this->assertSame($target->documentChunks()->count(), $target->expected_point_count);
        $this->assertNotNull($target->verified_at);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $target->point_manifest_digest);
        $this->assertSame(1, WorkspaceCorpusGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', WorkspaceCorpusGenerationStatus::Active->value)
            ->count());
    }

    public function test_rollback_reverifies_then_atomically_promotes_and_audits(): void
    {
        [$workspace, , $firstSparse] = $this->corpusFixture();
        $this->fakeCorpusService();
        $firstHybrid = app(RebuildHybridCorpusGeneration::class)->handle($workspace, $firstSparse);
        $secondSparse = SparseSpaceGeneration::factory()->available()->create([
            'embedding_space_generation_id' => $firstHybrid->embedding_space_generation_id,
        ]);
        $secondHybrid = app(RebuildHybridCorpusGeneration::class)->handle($workspace->fresh(), $secondSparse);
        $actor = User::factory()->create();

        $audit = app(RollbackWorkspaceCorpusGeneration::class)->handle(
            $workspace->fresh(),
            $firstHybrid->fresh(),
            $actor,
            'Restore the previously verified sparse configuration.',
        );

        $this->assertSame($firstHybrid->id, $workspace->fresh()->active_workspace_corpus_generation_id);
        $this->assertSame(WorkspaceCorpusGenerationStatus::Active, $firstHybrid->fresh()->status);
        $this->assertSame(WorkspaceCorpusGenerationStatus::Superseded, $secondHybrid->fresh()->status);
        $this->assertTrue($audit->actor->is($actor));
        $this->assertSame($secondHybrid->id, $audit->demoted_generation_id);
        $this->assertSame($firstHybrid->id, $audit->promoted_generation_id);
        $this->assertDatabaseCount('workspace_corpus_generation_rollbacks', 1);
    }

    public function test_rollback_rejects_incomplete_vector_projection(): void
    {
        [$workspace, , $sparse] = $this->corpusFixture();
        $this->fakeCorpusService();
        $hybrid = app(RebuildHybridCorpusGeneration::class)->handle($workspace, $sparse);
        $newSparse = SparseSpaceGeneration::factory()->available()->create([
            'embedding_space_generation_id' => $hybrid->embedding_space_generation_id,
        ]);
        app(RebuildHybridCorpusGeneration::class)->handle($workspace->fresh(), $newSparse);
        $this->forceIncompleteVerification = true;

        $this->expectException(RetrievalException::class);
        app(RollbackWorkspaceCorpusGeneration::class)->handle(
            $workspace->fresh(),
            $hybrid->fresh(),
            null,
            'This must fail closed.',
        );
    }

    public function test_clean_schema_contains_hybrid_lineage_without_raw_vectors(): void
    {
        $this->assertTrue(Schema::hasColumns('workspace_corpus_generations', [
            'rebuilt_from_generation_id',
            'rebuild_event_id',
            'sparse_space_generation_id',
            'expected_point_count',
            'point_manifest_digest',
            'verified_at',
        ]));
        $this->assertTrue(Schema::hasColumns('evidence_threshold_policies', [
            'reranker_provider',
            'reranker_model',
            'embedding_profile_fingerprint',
            'sparse_profile_fingerprint',
            'calibration_corpus_digest',
            'evidence_threshold',
        ]));
        $this->assertFalse(Schema::hasColumn('document_chunks', 'dense_vector'));
        $this->assertFalse(Schema::hasColumn('document_chunks', 'sparse_vector'));
    }

    /** @return array{Workspace, WorkspaceCorpusGeneration, SparseSpaceGeneration} */
    private function corpusFixture(): array
    {
        $workspace = Workspace::factory()->create();
        $source = WorkspaceCorpusGeneration::factory()->active()->create([
            'workspace_id' => $workspace->id,
        ]);
        $workspace->forceFill(['active_workspace_corpus_generation_id' => $source->id])->save();
        foreach (range(1, 2) as $ordinal) {
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
        $sparse = SparseSpaceGeneration::factory()->available()->create([
            'embedding_space_generation_id' => $source->embedding_space_generation_id,
        ]);

        return [$workspace, $source, $sparse];
    }

    private function fakeCorpusService(): void
    {
        $identity = app(DeterministicVectorPointIdentity::class);
        Http::fake(function (Request $request) use ($identity) {
            $payload = $request->data();
            $chunks = $payload['chunks'] ?? $payload['points'];
            $pointIds = collect($chunks)->map(fn (array $chunk): string => $identity->forChunk(
                $payload['vector_space']['embedding_space_generation_id'],
                $payload['workspace_id'],
                $payload['workspace_corpus_generation_id'],
                $chunk['chunk_id'],
            ))->values()->all();

            $verification = str_ends_with($request->url(), '/corpus/verify');

            return Http::response([
                'contract_version' => 1,
                'request_id' => $payload['request_id'],
                'complete' => ! ($verification && $this->forceIncompleteVerification),
                'point_ids' => $verification && $this->forceIncompleteVerification ? [] : $pointIds,
            ]);
        });
    }
}
