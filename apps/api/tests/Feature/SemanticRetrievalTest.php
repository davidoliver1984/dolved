<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Retrieval\RetrieveWorkspaceEvidence;
use App\Enums\DocumentStatus;
use App\Enums\EvidenceThresholdPolicyStatus;
use App\Enums\RetrievalOutcome;
use App\Enums\RetrievalTemporalMode;
use App\Enums\RetrievalTemporalReferenceKind;
use App\Enums\WorkspaceRole;
use App\Exceptions\RetrievalPlannerException;
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
use App\Support\Retrieval\ClassifierLineage;
use App\Support\Retrieval\PlannerUsage;
use App\Support\Retrieval\RetrievalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
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
            'Question', RetrievalTemporalMode::Current, null, null, [], null, $this->lineage(), $this->plannerUsage(),
        ), CarbonImmutable::parse('2026-03-01'));
        $historical = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::ValidAtDate,
            CarbonImmutable::parse('2026-01-15'), null, [], null, $this->lineage(), $this->plannerUsage(),
        ), CarbonImmutable::parse('2026-03-01'));
        $compare = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Compare, null, null, [], null, $this->lineage(), $this->plannerUsage(),
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
            'Question', RetrievalTemporalMode::Current, null, null, ['Blackpool'], null, $this->lineage(), $this->plannerUsage(),
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
        $this->assertSame('ambiguous_location_reference', $ambiguous->reason);
    }

    public function test_period_and_historical_references_resolve_against_attained_authority(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $first = $this->eligibleDocument($workspace, $generation, '2024-01-01', '2024-01-01');
        $neverAuthoritative = Document::factory()->create([
            'workspace_id' => $workspace->id,
            'document_family_id' => $first->document_family_id,
            'predecessor_document_id' => $first->id,
            'effective_from' => CarbonImmutable::parse('2025-06-01'),
        ]);
        $second = Document::factory()->indexed()->approved()->create([
            'workspace_id' => $workspace->id,
            'document_family_id' => $first->document_family_id,
            'predecessor_document_id' => $neverAuthoritative->id,
            'effective_from' => CarbonImmutable::parse('2026-02-15'),
            'approved_at' => CarbonImmutable::parse('2026-02-01'),
        ]);
        $this->assignChunk($second, $generation);
        $scope = app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace->public_id);
        $resolver = app(EligibilityResolver::class);
        $evaluatedAt = CarbonImmutable::parse('2026-08-01');

        $period = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::ValidAtDate,
            null,
            ['kind' => RetrievalTemporalReferenceKind::CalendarPeriod, 'value' => '2025'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $version = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::HistoricalReference,
            null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'version 1'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $secondVersion = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::HistoricalReference,
            null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'version 2'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $old = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::HistoricalReference,
            null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'old policy'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $yearQualified = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::HistoricalReference,
            null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'the 2025 policy'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $ambiguous = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::ValidAtDate,
            null,
            ['kind' => RetrievalTemporalReferenceKind::CalendarPeriod, 'value' => '2026'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $neverAttained = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::HistoricalReference,
            null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'version 3'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $unsupported = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::ValidAtDate,
            null,
            ['kind' => RetrievalTemporalReferenceKind::CalendarPeriod, 'value' => 'last winter'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $historicallyAmbiguous = $resolver->handle($scope, new RetrievalPlan(
            'Question',
            RetrievalTemporalMode::HistoricalReference,
            null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'version 1 or version 2'],
            [],
            null,
            $this->lineage(),
            $this->plannerUsage(),
        ), $evaluatedAt);
        $compareExact = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Compare, CarbonImmutable::parse('2025-01-01'),
            null, [], null, $this->lineage(), $this->plannerUsage(),
        ), $evaluatedAt);
        $comparePeriod = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Compare, null,
            ['kind' => RetrievalTemporalReferenceKind::CalendarPeriod, 'value' => '2025'],
            [], null, $this->lineage(), $this->plannerUsage(),
        ), $evaluatedAt);
        $compareHistorical = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Compare, null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'version 1'],
            [], null, $this->lineage(), $this->plannerUsage(),
        ), $evaluatedAt);
        $beforeWithdrawal = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::HistoricalReference, null,
            ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'before it was withdrawn, version 2'],
            [], null, $this->lineage(), $this->plannerUsage(),
        ), $evaluatedAt);

        $this->assertSame([$first->public_id], $period->documentPublicIdsBySide['primary']);
        $this->assertSame([$first->public_id], $version->documentPublicIdsBySide['primary']);
        $this->assertSame([$second->public_id], $secondVersion->documentPublicIdsBySide['primary']);
        $this->assertSame([$first->public_id], $old->documentPublicIdsBySide['primary']);
        $this->assertSame([$first->public_id], $yearQualified->documentPublicIdsBySide['primary']);
        $this->assertSame('ambiguous_authority_window_for_period', $ambiguous->reason);
        $this->assertSame('historical_reference_unresolved', $neverAttained->reason);
        $this->assertSame('unresolvable_temporal_period', $unsupported->reason);
        $this->assertSame('ambiguous_historical_reference', $historicallyAmbiguous->reason);
        $this->assertSame([$second->public_id], $beforeWithdrawal->documentPublicIdsBySide['primary']);
        foreach ([$compareExact, $comparePeriod, $compareHistorical] as $comparison) {
            $this->assertSame([$second->public_id], $comparison->documentPublicIdsBySide['primary']);
            $this->assertSame([$first->public_id], $comparison->documentPublicIdsBySide['comparison']);
        }
    }

    public function test_plural_location_references_select_descendant_and_fail_closed_for_unrelated_sites(): void
    {
        [$workspace, $user, $generation] = $this->retrievalWorkspace();
        $region = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'North West']);
        $site = OrganisationalLocation::factory()->for($workspace)->create([
            'name' => 'Harbour View',
            'parent_id' => $region->id,
        ]);
        $other = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Oak House']);
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-01');
        DB::table('document_applicability_snapshots')->where('document_id', $document->id)
            ->update(['sealed_at' => null, 'scope' => 'specific']);
        $document->applicabilitySnapshot->locations()->attach($site->id, ['workspace_id' => $workspace->id]);
        $document->applicabilitySnapshot->update(['sealed_at' => now()]);
        $scope = app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace->public_id);
        $resolver = app(EligibilityResolver::class);

        $hierarchy = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Current, null, null,
            ['North West', 'the Harbour View'], null, $this->lineage(), $this->plannerUsage(),
        ), CarbonImmutable::parse('2026-03-01'));
        $unrelated = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Current, null, null,
            ['Harbour View', $other->name], null, $this->lineage(), $this->plannerUsage(),
        ), CarbonImmutable::parse('2026-03-01'));
        OrganisationalLocation::factory()->for($workspace)->create(['name' => 'The Harbour View']);
        $exactArticle = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Current, null, null,
            ['The Harbour View'], null, $this->lineage(), $this->plannerUsage(),
        ), CarbonImmutable::parse('2026-03-01'));
        $generic = $resolver->handle($scope, new RetrievalPlan(
            'Question', RetrievalTemporalMode::Current, null, null,
            ['the home'], null, $this->lineage(), $this->plannerUsage(),
        ), CarbonImmutable::parse('2026-03-01'));

        $this->assertSame([$document->public_id], $hierarchy->documentPublicIdsBySide['primary']);
        $this->assertSame('multiple_unrelated_location_references', $unrelated->reason);
        $this->assertSame(RetrievalOutcome::NoEligibleEvidence, $exactArticle->outcome);
        $this->assertSame('unresolved_location_reference', $generic->reason);
    }

    public function test_response_v2_plan_validation_rejects_inconsistent_or_free_text_contract_values(): void
    {
        $base = [
            'retrieval_queries' => ['Question'],
            'temporal_mode' => 'current',
            'explicit_date' => null,
            'temporal_reference' => null,
            'location_references' => [],
            'clarification_reason' => null,
        ];
        $plan = RetrievalPlan::fromArray($base, 'Question', $this->lineagePayload(), $this->plannerUsagePayload());
        $this->assertSame(RetrievalTemporalMode::Current, $plan->temporalMode);

        $this->expectException(InvalidArgumentException::class);
        RetrievalPlan::fromArray(array_merge($base, [
            'temporal_mode' => 'clarification_required',
            'clarification_reason' => 'free_text_reason',
        ]), 'Question', $this->lineagePayload(), $this->plannerUsagePayload());
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
                return Http::response($this->planResponse($body));
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

            return Http::response($this->planResponse($body, [
                'temporal_mode' => 'clarification_required',
                'clarification_reason' => 'unclassifiable_temporal_intent',
            ]));
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
                return Http::response($this->planResponse($body));
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

            return Http::response($this->planResponse($body));
        });

        $plan = app(RetrievalClient::class)->plan(
            $workspace,
            'Current?',
            CarbonImmutable::parse('2026-08-07T12:00:00Z'),
        );

        $this->assertSame(RetrievalTemporalMode::Current, $plan->temporalMode);
        $this->assertCount(2, array_unique($requestIds));
    }

    public function test_semantic_planner_failure_is_typed_and_is_not_retried_to_success(): void
    {
        $workspace = Workspace::factory()->create();
        Http::fake(Http::response([
            'detail' => [
                'code' => 'retrieval_planning_failed',
                'category' => 'invalid_typed_plan',
                'provider_status' => 200,
                'attempt_count' => 1,
                'systemic' => false,
            ],
        ], 503));

        try {
            app(RetrievalClient::class)->plan(
                $workspace,
                'Current?',
                CarbonImmutable::parse('2026-08-07T12:00:00Z'),
            );
            $this->fail('Expected a typed planner failure.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame('invalid_typed_plan', $exception->category);
            $this->assertSame(200, $exception->providerStatus);
            $this->assertSame(1, $exception->attemptCount);
            $this->assertFalse($exception->systemic);
        }
        Http::assertSentCount(1);
    }

    public function test_systemic_planner_failure_is_typed_and_aborts_without_retry(): void
    {
        $workspace = Workspace::factory()->create();
        Http::fake(Http::response([
            'detail' => [
                'code' => 'retrieval_planning_failed',
                'category' => 'provider_quota_exhausted',
                'provider_status' => 429,
                'attempt_count' => 1,
                'systemic' => true,
            ],
        ], 503));

        try {
            app(RetrievalClient::class)->plan(
                $workspace,
                'Current?',
                CarbonImmutable::parse('2026-08-07T12:00:00Z'),
            );
            $this->fail('Expected a systemic planner failure.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame('provider_quota_exhausted', $exception->category);
            $this->assertSame(429, $exception->providerStatus);
            $this->assertTrue($exception->systemic);
        }
        Http::assertSentCount(1);
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
                null,
                [],
                null,
                $this->lineage(),
                $this->plannerUsage(),
            ),
            CarbonImmutable::parse('2026-03-01'),
        );
        $this->assertSame(RetrievalOutcome::ComparisonScopeIncomplete, $comparison->outcome);

        $workspace->active_workspace_corpus_generation_id = null;
        $workspace->save();
        Http::fake(function (Request $request) {
            $body = $request->data();

            return Http::response($this->planResponse($body));
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
                return Http::response($this->planResponse($body));
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

    public function test_local_evaluation_pair_shares_one_plan_and_keeps_calibrating_policy_off_public_resolution(): void
    {
        [$workspace, $user, $generation, $policy] = $this->hybridRetrievalWorkspace();
        $policy->update([
            'status' => EvidenceThresholdPolicyStatus::Calibrating,
            'activated_at' => null,
        ]);
        $document = $this->eligibleDocument($workspace, $generation, '2026-01-01', '2026-01-02');
        $chunk = $document->chunks()->firstOrFail();
        $this->fakeHybridRetrieval($generation, $policy->fresh(), $document, $chunk, 0.91);
        $scope = app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace->public_id);

        $pair = app(RetrieveWorkspaceEvidence::class)->handlePairForLocalEvaluation(
            $scope,
            'What is the current policy?',
            40,
            CarbonImmutable::parse('2026-08-01T12:00:00Z'),
            $policy->fresh(),
        );

        $this->assertSame(RetrievalOutcome::EvidenceFound, $pair['dense']['result']->outcome);
        $this->assertSame(RetrievalOutcome::EvidenceFound, $pair['hybrid']['result']->outcome);
        $this->assertSame($chunk->public_id, $pair['hybrid']['trace']['search']['diagnostics'][0]['fused_candidates'][0]['chunk_id']);
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/plan'));
        $this->assertSame(1, collect(Http::recorded())->filter(
            fn (array $pair): bool => str_ends_with($pair[0]->url(), '/plan'),
        )->count());

        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/retrieval",
            ['question' => 'What is the current policy?'],
        )->assertJsonPath('data.outcome', 'retrieval_failed');
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
                return Http::response($this->planResponse($body));
            }
            if (str_ends_with($request->url(), '/search')) {
                $hybrid = isset($body['hybrid_configuration']);
                $candidate = [
                    'chunk_id' => $chunk->public_id,
                    'document_id' => $document->public_id,
                    'workspace_corpus_generation_id' => $generation->public_id,
                    'embedding_space_generation_id' => $generation->embeddingSpaceGeneration->public_id,
                    'sparse_space_generation_id' => $generation->sparseSpaceGeneration->public_id,
                    'score' => $hybrid ? 0.04 : 0.8,
                    'rank' => 1,
                    'retrieval_method' => $hybrid ? 'hybrid' : 'dense',
                    'side' => 'primary',
                ];
                if ($hybrid) {
                    $candidate += [
                        'dense_score' => 0.8,
                        'dense_rank' => 1,
                        'sparse_score' => 8.0,
                        'sparse_rank' => 1,
                    ];
                }

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
                    'candidates' => [$candidate],
                    'diagnostics' => ($body['capture_diagnostics'] ?? false) ? [[
                        'side' => 'primary',
                        'dense_candidates' => [[...$candidate, 'score' => 0.8, 'rank' => 1, 'retrieval_method' => 'dense']],
                        'sparse_candidates' => $hybrid ? [[...$candidate, 'score' => 8.0, 'rank' => 1, 'retrieval_method' => 'sparse']] : [],
                        'fused_candidates' => $hybrid ? [$candidate] : [],
                    ]] : [],
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
                'provider_input_tokens' => 17,
                'provider_retry_count' => 0,
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

    private function lineage(): ClassifierLineage
    {
        $value = $this->lineagePayload();

        return ClassifierLineage::fromArray($value);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planResponse(array $body, array $overrides = []): array
    {
        return [
            'contract_version' => 2,
            'request_id' => $body['request_id'],
            'plan' => array_merge([
                'retrieval_queries' => [$body['question']],
                'temporal_mode' => 'current',
                'explicit_date' => null,
                'temporal_reference' => null,
                'location_references' => [],
                'clarification_reason' => null,
            ], $overrides),
            'classifier_lineage' => $this->lineagePayload(),
            'usage' => $this->plannerUsagePayload(),
        ];
    }

    /** @return array<string, string> */
    private function lineagePayload(): array
    {
        $parts = [
            'provider' => 'deterministic',
            'model' => 'fixed-retrieval-planner',
            'contract_schema_version' => 'plan-response-v2',
            'prompt_version' => 'fixed-v1',
            'adapter_version' => 'fixed-v1',
        ];
        $canonical = $parts;
        ksort($canonical);

        return $parts + [
            'fingerprint' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }

    private function plannerUsage(): PlannerUsage
    {
        return PlannerUsage::fromArray($this->plannerUsagePayload());
    }

    /** @return array<string, mixed> */
    private function plannerUsagePayload(): array
    {
        return [
            'stage' => 'planner',
            'provider' => 'deterministic',
            'model' => 'fixed-retrieval-planner',
            'execution' => 'local',
            'request_count' => 1,
            'retry_count' => 0,
            'input_tokens' => null,
            'cached_input_tokens' => null,
            'output_tokens' => null,
            'latency_ms' => 0,
            'cost_basis' => 'zero_cost_local',
            'cost_usd' => 0,
            'pricing_snapshot' => null,
        ];
    }
}
