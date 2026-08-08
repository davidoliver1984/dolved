<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\RetrievalOutcome;
use App\Enums\RetrievalTemporalMode;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\EvidenceThresholdPolicy;
use App\Models\OrganisationalLocation;
use App\Models\OrganisationalLocationAlias;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Models\WorkspaceMembership;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Services\Retrieval\EligibilityResolver;
use App\Services\Retrieval\RetrievalClient;
use App\Support\Retrieval\RetrievalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemanticRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_valid_at_date_and_compare_resolve_only_indexed_corpus_evidence(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $first = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $second = Document::factory()->indexed()->approved()->create([
            'workspace_id' => $workspace->id,
            'document_family_id' => $first->document_family_id,
            'predecessor_document_id' => $first->id,
            'effective_from' => CarbonImmutable::parse('2026-02-01'),
            'approved_at' => CarbonImmutable::parse('2026-02-02'),
        ]);
        $this->assignChunk($second, $generation);
        $scope = app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace->public_id);
        $resolver = app(EligibilityResolver::class);

        $current = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Current, null, null, null, null, null,
        ), CarbonImmutable::parse('2026-03-01'));
        $historical = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::ValidAtDate,
            CarbonImmutable::parse('2026-01-15'), null, null, null, null,
        ), CarbonImmutable::parse('2026-03-01'));
        $compare = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Compare, null,
            ['kind' => 'current'], ['kind' => 'previous'], null, null,
        ), CarbonImmutable::parse('2026-03-01'));

        $this->assertSame([$second->public_id], $current->documentPublicIdsBySide['primary']);
        $this->assertSame([$first->public_id], $historical->documentPublicIdsBySide['primary']);
        $this->assertSame([$second->public_id], $compare->documentPublicIdsBySide['primary']);
        $this->assertSame([$first->public_id], $compare->documentPublicIdsBySide['comparison']);
    }

    public function test_location_alias_narrows_by_ancestor_and_ambiguous_reference_clarifies(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $region = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'North West']);
        $site = OrganisationalLocation::factory()->for($workspace)->create([
            'name' => 'Blackpool Site', 'parent_id' => $region->id,
        ]);
        $alias = new OrganisationalLocationAlias(['alias' => 'Blackpool']);
        $alias->workspace_id = $workspace->id;
        $alias->organisational_location_id = $site->id;
        $alias->save();
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        DB::table('document_applicability_snapshots')->where('document_id', $document->id)
            ->update(['sealed_at' => null, 'scope' => 'specific']);
        $document->applicabilitySnapshot->locations()->attach($region->id, ['workspace_id' => $workspace->id]);
        $document->applicabilitySnapshot->update(['sealed_at' => now()]);
        $scope = app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace->public_id);
        $plan = new RetrievalPlan(
            'Question', RetrievalTemporalMode::Current, null, null, null, 'Blackpool', null,
        );

        $resolved = app(EligibilityResolver::class)->handle(
            $scope, $plan, CarbonImmutable::parse('2026-03-01'),
        );
        $this->assertSame([$document->public_id], $resolved->documentPublicIdsBySide['primary']);

        OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Blackpool']);
        $ambiguous = app(EligibilityResolver::class)->handle(
            $scope, $plan, CarbonImmutable::parse('2026-03-01'),
        );
        $this->assertSame(RetrievalOutcome::ClarificationRequired, $ambiguous->outcome);
        $this->assertSame('ambiguous_applicability_reference', $ambiguous->reason);
    }

    public function test_authenticated_endpoint_hydrates_candidates_and_rechecks_scope(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $chunk = $document->chunks()->firstOrFail();
        Http::fake(function (Request $request) use ($document, $chunk, $generation) {
            $body = $request->data();
            $requestId = $body['request_id'];
            if (str_ends_with($request->url(), '/plan')) {
                return Http::response([
                    'contract_version' => 1,
                    'request_id' => $requestId,
                    'plan' => [
                        'retrieval_queries' => [$body['question']],
                        'temporal_mode' => 'current',
                    ],
                ]);
            }

            return Http::response([
                'contract_version' => 1,
                'request_id' => $requestId,
                'lineage' => [
                    'embedding_profile_fingerprint' => $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint,
                ],
                'candidates' => [[
                    'chunk_id' => $chunk->public_id,
                    'document_id' => $document->public_id,
                    'workspace_corpus_generation_id' => $generation->public_id,
                    'embedding_space_generation_id' => $generation->embeddingSpaceGeneration->public_id,
                    'score' => 0.82,
                    'rank' => 1,
                    'retrieval_method' => 'dense',
                    'side' => 'primary',
                ]],
            ]);
        });

        $response = $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'What is the current policy?'],
        );

        $response->assertOk()
            ->assertJsonPath('data.outcome', 'evidence_found')
            ->assertJsonPath('data.candidates.0.chunk_id', $chunk->public_id)
            ->assertJsonPath('data.candidates.0.chunk_text', $chunk->text)
            ->assertJsonPath('data.candidates.0.document_family_id', $document->family->public_id);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Retrieval-Caller-Purpose', 'retrieval.plan')
            || $request->hasHeader('X-Retrieval-Caller-Purpose', 'retrieval.search'));
    }

    public function test_unauthorised_workspace_is_concealed_and_clarification_never_searches(): void
    {
        [$workspace, $user] = $this->retrievalWorkspace();
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($otherUser)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'Secret?'],
        )->assertNotFound();

        Http::fake(function (Request $request) {
            $body = $request->data();

            return Http::response([
                'contract_version' => 1,
                'request_id' => $body['request_id'],
                'plan' => [
                    'retrieval_queries' => [$body['question']],
                    'temporal_mode' => 'clarification_required',
                    'clarification_reason' => 'ambiguous_temporal_reference',
                ],
            ]);
        });
        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'What did the old version say?'],
        )->assertJsonPath('data.outcome', 'clarification_required');
        Http::assertSentCount(1);
    }

    public function test_final_recheck_drops_a_candidate_that_became_ineligible(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $chunk = $document->chunks()->firstOrFail();
        Http::fake(function (Request $request) use ($document, $chunk, $generation) {
            $body = $request->data();
            if (str_ends_with($request->url(), '/plan')) {
                return Http::response([
                    'request_id' => $body['request_id'],
                    'plan' => [
                        'retrieval_queries' => [$body['question']],
                        'temporal_mode' => 'current',
                    ],
                ]);
            }
            DB::table('documents')->where('id', $document->id)->update([
                'status' => 'failed',
                'failure_category' => 'synthetic_race',
                'failure_message' => 'Synthetic eligibility race.',
            ]);

            return Http::response([
                'request_id' => $body['request_id'],
                'lineage' => [
                    'embedding_profile_fingerprint' => $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint,
                ],
                'candidates' => [[
                    'chunk_id' => $chunk->public_id,
                    'document_id' => $document->public_id,
                    'workspace_corpus_generation_id' => $generation->public_id,
                    'embedding_space_generation_id' => $generation->embeddingSpaceGeneration->public_id,
                    'score' => 0.9,
                    'rank' => 1,
                    'retrieval_method' => 'dense',
                    'side' => 'primary',
                ]],
            ]);
        });

        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'Current?'],
        )->assertJsonPath('data.outcome', 'no_retrieval_candidates');
    }

    public function test_bounded_retry_mints_a_fresh_signed_request_id(): void
    {
        $workspace = Workspace::factory()->create();
        $requestIds = [];
        Http::fake(function (Request $request) use (&$requestIds) {
            $requestIds[] = $request->header('X-Retrieval-Caller-Request-ID')[0] ?? null;
            if (count($requestIds) === 1) {
                return Http::response([], 503);
            }
            $body = $request->data();

            return Http::response([
                'request_id' => $body['request_id'],
                'plan' => [
                    'retrieval_queries' => [$body['question']],
                    'temporal_mode' => 'current',
                ],
            ]);
        });

        $plan = app(RetrievalClient::class)->plan(
            $workspace,
            'Current?',
            CarbonImmutable::parse('2026-08-07T12:00:00Z'),
        );

        $this->assertSame(RetrievalTemporalMode::Current, $plan->temporalMode);
        $this->assertCount(2, array_unique($requestIds));
    }

    public function test_incomplete_comparison_and_empty_eligible_scope_are_controlled_outcomes(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $scope = app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace->public_id);
        $comparison = app(EligibilityResolver::class)->handle(
            $scope,
            new RetrievalPlan(
                'Question',
                RetrievalTemporalMode::Compare,
                null,
                ['kind' => 'current'],
                ['kind' => 'previous'],
                null,
                null,
            ),
            CarbonImmutable::parse('2026-03-01'),
        );
        $this->assertSame(RetrievalOutcome::ComparisonScopeIncomplete, $comparison->outcome);

        $workspace->active_workspace_corpus_generation_id = null;
        $workspace->save();
        Http::fake(function (Request $request) {
            $body = $request->data();

            return Http::response([
                'request_id' => $body['request_id'],
                'plan' => [
                    'retrieval_queries' => [$body['question']],
                    'temporal_mode' => 'current',
                ],
            ]);
        });
        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'Current?'],
        )->assertJsonPath('data.outcome', 'no_eligible_evidence');
        Http::assertSentCount(1);
    }

    public function test_empty_search_returns_no_retrieval_candidates(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        Http::fake(function (Request $request) use ($generation) {
            $body = $request->data();
            if (str_ends_with($request->url(), '/plan')) {
                return Http::response([
                    'request_id' => $body['request_id'],
                    'plan' => [
                        'retrieval_queries' => [$body['question']],
                        'temporal_mode' => 'current',
                    ],
                ]);
            }

            return Http::response([
                'request_id' => $body['request_id'],
                'lineage' => [
                    'embedding_profile_fingerprint' => $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint,
                ],
                'candidates' => [],
            ]);
        });
        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'No vectors?'],
        )->assertJsonPath('data.outcome', 'no_retrieval_candidates');
    }

    public function test_operational_failure_is_distinct_from_an_empty_search(): void
    {
        [$workspace, $user] = $this->retrievalWorkspace();

        Http::fake(fn () => Http::response([], 503));
        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'Unavailable?'],
        )->assertJsonPath('data.outcome', 'retrieval_failed');
    }

    public function test_hybrid_path_reranks_rechecks_and_applies_laravel_threshold(): void
    {
        [$workspace, $user, $generation, $policy] = $this->hybridRetrievalWorkspace();
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $chunk = $document->chunks()->firstOrFail();
        $this->fakeHybridRetrieval($generation, $policy, $document, $chunk, 0.79);

        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'What is the current policy?'],
        )->assertOk()->assertJsonPath('data.outcome', 'insufficient_evidence');

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'X-Retrieval-Caller-Purpose',
            'retrieval.rerank',
        ));
    }

    public function test_hybrid_path_returns_qualified_lineage(): void
    {
        [$workspace, $user, $generation, $policy] = $this->hybridRetrievalWorkspace();
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $chunk = $document->chunks()->firstOrFail();
        $this->fakeHybridRetrieval($generation, $policy, $document, $chunk, 0.91);

        $response = $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'What is the current policy?'],
        );

        $response->assertOk()
            ->assertJsonPath('data.outcome', 'evidence_found')
            ->assertJsonPath('data.candidates.0.score', 0.91)
            ->assertJsonPath('data.candidates.0.fused_score', 0.04)
            ->assertJsonPath('data.candidates.0.evidence_threshold_policy_version', $policy->version)
            ->assertJsonPath('data.candidates.0.reranker_model', $policy->reranker_model);
    }

    public function test_hybrid_path_rejects_mismatched_sparse_lineage(): void
    {
        [$workspace, $user, $generation, $policy] = $this->hybridRetrievalWorkspace();
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $chunk = $document->chunks()->firstOrFail();
        $this->fakeHybridRetrieval($generation, $policy, $document, $chunk, 0.91, true);

        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'What is the current policy?'],
        )->assertOk()->assertJsonPath('data.outcome', 'retrieval_failed');

        Http::assertSentCount(2);
    }

    /** @return array{Workspace, User, WorkspaceCorpusGeneration} */
    private function retrievalWorkspace(): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($workspace)->for($user)->create([
            'role' => WorkspaceRole::Owner,
        ]);
        $profile = EmbeddingProfile::factory()->create();
        $space = EmbeddingSpaceGeneration::factory()->available()->create([
            'embedding_profile_id' => $profile->id,
            'dimensions' => $profile->dimensions,
        ]);
        $generation = WorkspaceCorpusGeneration::factory()->active()->create([
            'workspace_id' => $workspace->id,
            'embedding_space_generation_id' => $space->id,
        ]);
        $workspace->active_workspace_corpus_generation_id = $generation->id;
        $workspace->save();

        return [$workspace->fresh(), $user, $generation->fresh(['embeddingSpaceGeneration.embeddingProfile'])];
    }

    /** @return array{Workspace, User, WorkspaceCorpusGeneration, EvidenceThresholdPolicy} */
    private function hybridRetrievalWorkspace(): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($workspace)->for($user)->create([
            'role' => WorkspaceRole::Owner,
        ]);
        $profile = EmbeddingProfile::factory()->create();
        $space = EmbeddingSpaceGeneration::factory()->available()->create([
            'embedding_profile_id' => $profile->id,
            'dimensions' => $profile->dimensions,
        ]);
        $sparseProfile = SparseEmbeddingProfile::factory()->create();
        $sparse = SparseSpaceGeneration::factory()->available()->create([
            'sparse_embedding_profile_id' => $sparseProfile->id,
            'embedding_space_generation_id' => $space->id,
        ]);
        $generation = WorkspaceCorpusGeneration::factory()->active()->create([
            'workspace_id' => $workspace->id,
            'embedding_space_generation_id' => $space->id,
            'sparse_space_generation_id' => $sparse->id,
            'expected_point_count' => 1,
            'point_manifest_digest' => hash('sha256', 'hybrid-test'),
            'verified_at' => now(),
        ]);
        $workspace->active_workspace_corpus_generation_id = $generation->id;
        $workspace->save();
        $policy = EvidenceThresholdPolicy::factory()->active()->create([
            'embedding_profile_fingerprint' => $profile->fingerprint,
            'sparse_profile_fingerprint' => $sparseProfile->fingerprint,
        ]);

        return [
            $workspace->fresh(),
            $user,
            $generation->fresh([
                'embeddingSpaceGeneration.embeddingProfile',
                'sparseSpaceGeneration.sparseEmbeddingProfile',
            ]),
            $policy,
        ];
    }

    private function fakeHybridRetrieval(
        WorkspaceCorpusGeneration $generation,
        EvidenceThresholdPolicy $policy,
        Document $document,
        DocumentChunk $chunk,
        float $rerankerScore,
        bool $mismatchedLineage = false,
    ): void {
        Http::fake(function (Request $request) use ($generation, $policy, $document, $chunk, $rerankerScore, $mismatchedLineage) {
            $body = $request->data();
            if (str_ends_with($request->url(), '/plan')) {
                return Http::response([
                    'request_id' => $body['request_id'],
                    'plan' => [
                        'retrieval_queries' => [$body['question']],
                        'temporal_mode' => 'current',
                    ],
                ]);
            }
            if (str_ends_with($request->url(), '/search')) {
                return Http::response([
                    'request_id' => $body['request_id'],
                    'lineage' => [
                        'embedding_profile_fingerprint' => $policy->embedding_profile_fingerprint,
                        'sparse_profile_fingerprint' => $mismatchedLineage
                            ? str_repeat('0', 64)
                            : $policy->sparse_profile_fingerprint,
                        'sparse_space_generation_id' => $generation->sparseSpaceGeneration->public_id,
                        'fusion_strategy' => $policy->fusion_strategy,
                        'fusion_version' => $policy->fusion_version,
                        'rrf_k' => $policy->rrf_k,
                        'configuration_version' => $policy->version,
                    ],
                    'candidates' => [[
                        'chunk_id' => $chunk->public_id,
                        'document_id' => $document->public_id,
                        'workspace_corpus_generation_id' => $generation->public_id,
                        'embedding_space_generation_id' => $generation->embeddingSpaceGeneration->public_id,
                        'sparse_space_generation_id' => $generation->sparseSpaceGeneration->public_id,
                        'score' => 0.04,
                        'rank' => 1,
                        'retrieval_method' => 'hybrid',
                        'side' => 'primary',
                        'dense_score' => 0.8,
                        'dense_rank' => 1,
                        'sparse_score' => 8.0,
                        'sparse_rank' => 1,
                    ]],
                ]);
            }

            return Http::response([
                'request_id' => $body['request_id'],
                'profile' => [
                    'provider' => $policy->reranker_provider,
                    'model' => $policy->reranker_model,
                    'adapter_version' => $policy->reranker_adapter_version,
                    'truncation' => false,
                ],
                'candidates' => [[
                    'chunk_id' => $chunk->public_id,
                    'side' => 'primary',
                    'score' => $rerankerScore,
                    'rank' => 1,
                ]],
            ]);
        });
    }

    private function eligibleDocument(
        Workspace $workspace,
        WorkspaceCorpusGeneration $generation,
        string $effectiveFrom,
        string $approvedAt,
    ): Document {
        $document = Document::factory()->indexed()->approved()->create([
            'workspace_id' => $workspace->id,
            'effective_from' => CarbonImmutable::parse($effectiveFrom),
            'approved_at' => CarbonImmutable::parse($approvedAt),
            'status' => DocumentStatus::Indexed,
        ]);
        $this->assignChunk($document, $generation);

        return $document->fresh(['family', 'applicabilitySnapshot.locations']);
    }

    private function assignChunk(Document $document, WorkspaceCorpusGeneration $generation): DocumentChunk
    {
        $chunk = DocumentChunk::factory()->create([
            'workspace_id' => $document->workspace_id,
            'document_id' => $document->id,
        ]);
        WorkspaceCorpusGenerationChunk::factory()->create([
            'workspace_id' => $document->workspace_id,
            'workspace_corpus_generation_id' => $generation->id,
            'document_chunk_id' => $chunk->id,
        ]);

        return $chunk;
    }
}
