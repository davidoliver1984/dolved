<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Actions\Generation\GenerateGroundedAnswer;
use App\Actions\Retrieval\RetrieveWorkspaceEvidence;
use App\Enums\DocumentStatus;
use App\Enums\GenerationRunStatus;
use App\Enums\RetrievalOutcome;
use App\Enums\RetrievalTemporalMode;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\EvidenceThresholdPolicy;
use App\Models\IngestionEventClaim;
use App\Models\OrganisationalLocation;
use App\Models\OrganisationalLocationAlias;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceUsageEvent;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Services\Conversation\ControlledResponseRenderer;
use App\Services\Generation\GenerationProfileFactory;
use App\Services\Retrieval\EligibilityResolver;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Retrieval\ApplicabilityScopeFact;
use App\Support\Retrieval\EvidenceNeedsPlan;
use App\Support\Retrieval\RetrievalPlan;
use App\Support\Retrieval\RetrievalResult;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EvidenceNeedsRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public static function crossServiceCases(): array
    {
        return [['normal'], ['missing_history'], ['ungrounded']];
    }

    #[DataProvider('crossServiceCases')]
    public function test_cross_service_mixed_conversation_uses_real_python_fusion_and_generation_contract(string $case): void
    {
        $bridge = getenv('NEEDS_CROSS_SERVICE_BRIDGE');
        if (! is_string($bridge) || ! str_starts_with($bridge, 'http://ai:')) {
            $this->markTestSkipped('Opt-in isolated cross-service bridge is not running.');
        }
        Queue::fake();
        [$workspace, $generation] = $this->scope(true, true);
        $old = $this->document($workspace, $generation, '2026-03-01');
        $new = $this->document($workspace, $generation, '2026-06-15', $old);
        $support = $this->document($workspace, $generation, '2026-04-01');
        if ($case === 'missing_history') {
            $old->status = DocumentStatus::Deleting;
            $old->save();
        }
        [$foreign, $foreignGeneration] = $this->scope(true);
        $foreignDocument = $this->document($foreign, $foreignGeneration, '2026-01-01');
        $example = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/examples.json'), true, flags: JSON_THROW_ON_ERROR)[0];
        foreach ($example['plan']['needs'] as &$need) {
            $need['question_span']['turn_id'] = 'user-1';
            if ($need['authority']['source'] !== null) {
                $need['authority']['source']['turn_id'] = 'user-1';
            }
        }
        unset($need);
        if ($case === 'ungrounded') {
            $example['plan']['needs'][0]['question_span']['quote'] = 'Not present in authorised user text';
        }
        $fixtures = ['plan' => $example['plan'], 'chunks' => []];
        foreach ([$old, $new, $support, $foreignDocument] as $document) {
            $fixtures['chunks'][] = ['workspace_id' => $document->workspace->public_id,
                'document_id' => $document->public_id, 'chunk_id' => $document->chunks()->firstOrFail()->public_id];
        }
        config(['retrieval.caller.key_id' => 'cross-test', 'retrieval.caller.secret' => base64_encode(str_repeat('x', 32)),
            'conversation.needs_workspace_ids' => [$workspace->public_id]]);
        $user = WorkspaceMembership::query()->where('workspace_id', $workspace->id)->firstOrFail()->user;
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user,
            $example['turns'][0]['text'], (string) Str::uuid());
        $observations = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($bridge, $fixtures, $case, &$observations) {
            $headers = array_map(fn ($values) => $values[0], $request->headers());
            $client = new Client(['timeout' => 30, 'http_errors' => false]);
            $reply = $client->post($bridge, ['json' => ['path' => parse_url($request->url(), PHP_URL_PATH),
                'headers' => $headers, 'body' => $request->body(), 'fixtures' => $fixtures]]);
            $result = json_decode((string) $reply->getBody(), true, flags: JSON_THROW_ON_ERROR);
            if (isset($result['error'])) {
                $observations[] = $result;
                throw new \RuntimeException($result['message']);
            }
            if ($result['status'] !== 200) {
                $observations[] = ['response_failure' => $result];
            }
            $observations = [...$observations, ...$result['observations']];
            if ($case !== 'ungrounded') {
                $this->assertSame(200, $result['status'], json_encode($result));
            }

            return Http::response($result['body'], $result['status']);
        });
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        if ($case === 'ungrounded') {
            $this->assertSame(GenerationRunStatus::ClarificationRequired, $run->status);
            $this->assertSame('ungrounded_needs_reference', $run->retrievalOutcomeSnapshot->clarification_reason);
            $this->assertSame(['planner_transport'], array_column($observations, 'stage'));
            $this->assertNull($run->generatedAnswer);
            Http::assertSentCount(1);

            return;
        }
        $this->assertSame(GenerationRunStatus::Completed, $run->status, json_encode($observations));
        $this->assertSame(['n1' => $case === 'missing_history' ? 'unsupported' : 'supported', 'n2' => 'supported'], $run->generatedAnswer->aspect_results);
        $ids = $run->generatedAnswer->evidenceSnapshots->pluck('document_id')->all();
        if ($case === 'missing_history') {
            $this->assertNotContains($old->id, $ids);
        } else {
            $this->assertContains($old->id, $ids);
        }
        $this->assertContains($new->id, $ids);
        $this->assertContains($support->id, $ids);
        $this->assertNotContains($foreignDocument->id, $ids);
        $stages = array_count_values(array_column($observations, 'stage'));
        $this->assertSame(1, $stages['planner_transport']);
        $this->assertSame($case === 'missing_history' ? 1 : 3, $stages['production_fusion']);
        $this->assertSame($case === 'missing_history' ? 1 : 3, $stages['reranker_transport']);
        $this->assertSame(1, $stages['generation_transport']);
        Http::assertSentCount($case === 'missing_history' ? 4 : 6);
    }

    private function scope(bool $hybrid = false, bool $voyage = false): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => WorkspaceRole::Owner]);
        $profile = $voyage ? EmbeddingProfile::factory()->voyageV1()->create() : EmbeddingProfile::factory()->create();
        $space = EmbeddingSpaceGeneration::factory()->available()->create(['embedding_profile_id' => $profile->id, 'dimensions' => $profile->dimensions]);
        $policy = null;
        $sparse = null;
        if ($hybrid) {
            $sparseAttributes = SparseEmbeddingProfile::factory()->raw();
            if ($voyage) {
                $identity = Arr::except($sparseAttributes, ['public_id', 'fingerprint']);
                ksort($identity);
                $sparseAttributes['fingerprint'] = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            }
            $sparseProfile = SparseEmbeddingProfile::factory()->create($sparseAttributes);
            $sparse = SparseSpaceGeneration::factory()->available()->create(['sparse_embedding_profile_id' => $sparseProfile->id, 'embedding_space_generation_id' => $space->id]);
            $policy = EvidenceThresholdPolicy::factory()->active()->create(['embedding_profile_fingerprint' => $profile->fingerprint,
                'sparse_profile_fingerprint' => $sparseProfile->fingerprint,
                ...($voyage ? ['reranker_provider' => 'voyage', 'reranker_model' => 'rerank-2.5', 'reranker_adapter_version' => '1'] : [])]);
        }
        $generation = WorkspaceCorpusGeneration::factory()->active()->create(['workspace_id' => $workspace->id,
            'embedding_space_generation_id' => $space->id, 'sparse_space_generation_id' => $sparse?->id,
            'verified_at' => $hybrid ? now() : null]);
        $workspace->active_workspace_corpus_generation_id = $generation->id;
        $workspace->save();

        return [$workspace, $generation, app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace->public_id), $policy];
    }

    private function document(Workspace $workspace, WorkspaceCorpusGeneration $generation, string $at, ?Document $previous = null): Document
    {
        $attributes = ['workspace_id' => $workspace->id, 'effective_from' => $at, 'approved_at' => $at];
        if ($previous !== null) {
            $attributes += ['document_family_id' => $previous->document_family_id, 'predecessor_document_id' => $previous->id];
        }
        $document = Document::factory()->indexed()->approved()->create($attributes);
        $claim = IngestionEventClaim::factory()->create(['workspace_id' => $workspace->id, 'document_id' => $document->id]);
        $chunk = DocumentChunk::factory()->create(['workspace_id' => $workspace->id, 'document_id' => $document->id,
            'ingestion_event_claim_id' => $claim->id]);
        WorkspaceCorpusGenerationChunk::factory()->create(['workspace_id' => $workspace->id,
            'workspace_corpus_generation_id' => $generation->id, 'document_chunk_id' => $chunk->id]);

        return $document;
    }

    private function plan(int $example = 0, ?callable $mutate = null): EvidenceNeedsPlan
    {
        $data = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/examples.json'), true, flags: JSON_THROW_ON_ERROR)[$example];
        if ($mutate !== null) {
            $data = $mutate($data);
        }
        $lineage = ['provider' => 'openai', 'model' => 'gpt-5-mini', 'contract_schema_version' => 'evidence-needs-plan-v3',
            'prompt_version' => 'bounded-evidence-needs-v20', 'adapter_version' => 'structured-chat-needs-v7'];
        ksort($lineage);
        $lineage['fingerprint'] = hash('sha256', json_encode($lineage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return EvidenceNeedsPlan::fromArray($data['plan'], $data['turns'], $lineage,
            ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini', 'execution' => 'provider_api',
                'request_count' => 1, 'retry_count' => 0, 'latency_ms' => 1, 'cost_basis' => 'unavailable']);
    }

    private function scopePlan(Document $document, string $question = 'Does Equipment Policy cover Harbour Place?', string $location = 'Harbour Place', string $scopeQuestion = 'membership'): EvidenceNeedsPlan
    {
        return $this->plan(4, function ($data) use ($document, $question, $location, $scopeQuestion) {
            $data['turns'] = [['turn_id' => 'user-1', 'text' => $question]];
            $data['plan']['needs'] = [[
                'need_id' => 'n1', 'aspect' => $question, 'central' => true,
                'question_span' => ['turn_id' => 'user-1', 'quote' => $question],
                'operation' => 'scope_enquiry', 'authority' => ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null],
                'fact_reference' => null, 'applicability' => ['kind' => 'named', 'source' => ['turn_id' => 'user-1', 'quote' => $location]],
                'scope_question' => $scopeQuestion, 'claim_under_test' => null,
                'supports_need_id' => null,
            ]];
            $data['plan']['bindings'] = [['need_id' => 'n1', 'ownership' => 'authorised_workspace',
                'ownership_source' => null, 'subject_status' => 'matched', 'family_ids' => [$document->family->public_id]]];

            return $data;
        });
    }

    private function claimPlan(Document $document, string $question, string $claimQuote, ?string $site = null): EvidenceNeedsPlan
    {
        return $this->plan(0, function ($data) use ($document, $question, $claimQuote, $site) {
            $data['turns'] = [['turn_id' => 'user-1', 'text' => $question]];
            $data['plan']['needs'] = [[
                'need_id' => 'n1', 'aspect' => 'Where formal grievances are submitted', 'central' => true,
                'question_span' => ['turn_id' => 'user-1', 'quote' => $question],
                'operation' => 'retrieve', 'authority' => ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null],
                'fact_reference' => null,
                'applicability' => $site === null ? ['kind' => 'generic', 'source' => null]
                    : ['kind' => 'named', 'source' => ['turn_id' => 'user-1', 'quote' => $site]],
                'claim_under_test' => ['version' => 'claim-under-test-v1', 'type' => 'named_recipient',
                    'relation' => 'submission_destination', 'source' => ['turn_id' => 'user-1', 'quote' => $claimQuote]],
                'scope_question' => null, 'supports_need_id' => null,
            ]];
            $data['plan']['bindings'] = [['need_id' => 'n1', 'ownership' => 'authorised_workspace',
                'ownership_source' => null, 'subject_status' => 'matched', 'family_ids' => [$document->family->public_id]]];

            return $data;
        });
    }

    public function test_disputed_recipient_is_context_not_a_location_or_tenant_permission(): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        [$foreign, $foreignGeneration] = $this->scope();
        $foreignDocument = $this->document($foreign, $foreignGeneration, '2026-01-01');
        $question = 'Do formal grievances get raised in writing to the Brambleford Office People team?';
        $plan = $this->claimPlan($document, $question, 'Brambleford Office People team');
        $retrievalPlan = $plan->retrievalPlan('n1');
        $this->assertSame([], $retrievalPlan->locationReferences);
        $this->assertStringContainsString('Brambleford Office People team', $retrievalPlan->query);
        $this->assertSame('Brambleford Office People team', $retrievalPlan->evidenceNeed['claim_under_test']['source']['quote']);
        $eligible = app(EligibilityResolver::class)->handle($scope, $retrievalPlan, CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(RetrievalOutcome::EvidenceFound, $eligible->outcome);
        $this->assertContains($document->public_id, $eligible->documentPublicIdsBySide['primary']);
        $this->assertNotContains($foreignDocument->public_id, $eligible->documentPublicIdsBySide['primary']);
        $unknown = $this->claimPlan($document, 'Do grievances go to the Unknown Payroll Desk?', 'Unknown Payroll Desk');
        $this->assertSame([], $unknown->retrievalPlan('n1')->locationReferences);
        $this->assertSame(RetrievalOutcome::EvidenceFound,
            app(EligibilityResolver::class)->handle($scope, $unknown->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-08'))->outcome);

        $site = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Riverside House']);
        $mixed = $this->claimPlan($document,
            'At Riverside House, do formal grievances go to the Brambleford Office People team?',
            'Brambleford Office People team', 'Riverside House');
        $this->assertSame(['Riverside House'], $mixed->retrievalPlan('n1')->locationReferences);
        $this->assertSame('claim-under-test-v1', $mixed->retrievalPlan('n1')->evidenceNeed['claim_under_test']['version']);
        $this->assertSame(RetrievalOutcome::EvidenceFound,
            app(EligibilityResolver::class)->handle($scope, $mixed->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-08'))->outcome);
        $this->assertSame($workspace->id, $site->workspace_id);

        foreach (['Cinderhill House', 'Unlisted Region'] as $unresolvedSite) {
            $withSite = $this->claimPlan($document,
                'At '.$unresolvedSite.', do formal grievances go to the Brambleford Office People team?',
                'Brambleford Office People team', $unresolvedSite);
            $blocked = app(EligibilityResolver::class)->handle($scope, $withSite->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-08'));
            $this->assertSame(RetrievalOutcome::ClarificationRequired, $blocked->outcome);
            $this->assertSame('unresolved_location_reference', $blocked->reason);
        }
        Http::assertNothingSent();
    }

    public function test_disputed_claim_without_authorised_evidence_stays_no_answer(): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        DB::table('documents')->where('id', $document->id)->update([
            'governance_status' => 'withdrawn', 'withdrawn_at' => '2026-02-01',
        ]);
        $question = 'Do grievances go to the Unknown Recipient?';
        $plan = $this->claimPlan($document, $question, 'Unknown Recipient');
        $result = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $plan, 15, CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(RetrievalOutcome::InsufficientEvidence, $result->outcome);
        $this->assertSame([], $result->candidates);
        Http::assertNothingSent();
    }

    public static function scopeBases(): array
    {
        return [['universal'], ['direct_match'], ['inherited_match'], ['outside_recorded_scope']];
    }

    public static function breadthShapes(): array
    {
        return [['universal'], ['direct'], ['inherited']];
    }

    #[DataProvider('breadthShapes')]
    public function test_breadth_resolves_universal_direct_and_inherited_snapshots(string $shape): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        $region = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'North Region', 'kind' => 'region']);
        $site = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Harbour Place', 'kind' => 'service', 'parent_id' => $region->id]);
        if ($shape !== 'universal') {
            DB::table('document_applicability_snapshots')->where('document_id', $document->id)->update(['scope' => 'specific']);
            $document->applicabilitySnapshot->locations()->attach(
                $shape === 'direct' ? $site->id : $region->id, ['workspace_id' => $workspace->id]);
        }
        $question = 'What is the full recorded scope of Equipment Policy at Harbour Place?';
        $plan = $this->scopePlan($document, $question, 'Harbour Place', 'breadth');
        // A breadth request need not name a location at all.
        $planValue = $plan->value;
        $planValue['needs'][0]['applicability'] = ['kind' => 'generic', 'source' => null];
        $plan = $this->plan(4, fn ($data) => ['turns' => [['turn_id' => 'user-1', 'text' => $question]], 'plan' => $planValue]);
        $fact = app(EligibilityResolver::class)->scopeFact($scope, $plan->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-08'));
        $this->assertInstanceOf(ApplicabilityScopeFact::class, $fact);
        $this->assertSame('recorded_breadth', $fact->value['basis']);
        $this->assertSame($shape === 'universal' ? [] : [$shape === 'direct' ? $site->public_id : $region->public_id],
            array_column($fact->value['recorded_locations'], 'location_id'));
        $this->assertStringContainsString($shape === 'universal' ? 'is universal' : ($shape === 'direct' ? 'Harbour Place' : 'North Region'),
            $fact->renderedText());
        Http::assertNothingSent();
    }

    public function test_breadth_scope_fact_lists_the_full_sealed_snapshot_without_operational_chunks(): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        $document->family->update(['name' => 'Key Safe Procedure']);
        $region = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Midlands Region', 'kind' => 'region']);
        $site = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Willow Bank Community Service', 'kind' => 'service', 'parent_id' => $region->id]);
        DB::table('document_applicability_snapshots')->where('document_id', $document->id)->update(['scope' => 'specific']);
        $document->applicabilitySnapshot->locations()->attach([$region->id, $site->id], ['workspace_id' => $workspace->id]);
        $question = 'Is the Key Safe Procedure still just a Willow Bank thing?';
        $plan = $this->scopePlan($document, $question, 'Willow Bank', 'breadth');
        $alias = new OrganisationalLocationAlias(['alias' => 'Willow Bank']);
        $alias->workspace()->associate($workspace);
        $alias->organisationalLocation()->associate($site);
        $alias->save();
        $at = CarbonImmutable::parse('2026-09-08');
        $resolver = app(EligibilityResolver::class);
        $fact = $resolver->scopeFact($scope, $plan->retrievalPlan('n1'), $at);
        $this->assertInstanceOf(ApplicabilityScopeFact::class, $fact);
        $this->assertSame(ApplicabilityScopeFact::BREADTH_VERSION, $fact->value['representation']);
        $this->assertSame($document->applicabilitySnapshot->id, $fact->value['snapshot']['snapshot_id']);
        $this->assertEqualsCanonicalizing([$region->public_id, $site->public_id], array_column($fact->value['recorded_locations'], 'location_id'));
        $entry = collect($fact->value['recorded_locations'])->firstWhere('location_id', $site->public_id);
        $this->assertContains($region->public_id, $entry['ancestor_ids']);
        $this->assertStringContainsString('Midlands Region', $fact->renderedText());
        $this->assertStringContainsString('Willow Bank Community Service', $fact->renderedText());
        $this->assertStringContainsString('does not establish what action is safe or required', $fact->renderedText());
        $resolver->recheckScopeFact($scope, $fact->value);
        $retrieval = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $plan, 15, $at);
        $this->assertSame([], $retrieval->candidates);
        $this->assertSame($fact->value, $retrieval->evidenceNeeds[0]['scope_fact']);
        Http::assertNothingSent();
    }

    #[DataProvider('scopeBases')]
    public function test_scope_facts_use_real_resolver_and_never_dispatch_or_read_operational_chunks(string $basis): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        $document->family->update(['name' => 'Equipment Policy']);
        $parent = OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Northern Region']);
        $site = OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place', 'parent_id' => $parent->id]);
        if ($basis !== 'universal') {
            // Arrange a sealed synthetic snapshot; never mutate production fixtures.
            DB::table('document_applicability_snapshots')
                ->where('document_id', $document->id)->update(['scope' => 'specific']);
            $member = $basis === 'direct_match' ? $site : ($basis === 'inherited_match' ? $parent
                : OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id]));
            $document->applicabilitySnapshot->locations()->attach($member->id, ['workspace_id' => $workspace->id]);
            if ($basis === 'direct_match') {
                // A snapshot may list both the parent region and its child site.
                $document->applicabilitySnapshot->locations()->attach($parent->id, ['workspace_id' => $workspace->id]);
            }
        }
        $plan = $this->scopePlan($document);
        $at = CarbonImmutable::parse('2026-09-08');
        $resolver = app(EligibilityResolver::class);
        $fact = $resolver->scopeFact($scope, $plan->retrievalPlan('n1'), $at);
        $this->assertInstanceOf(ApplicabilityScopeFact::class, $fact);
        $this->assertSame($basis, $fact->value['basis']);
        $this->assertSame($basis === 'outside_recorded_scope' ? 'does_not_apply' : 'applies', $fact->value['result']);
        $resolver->recheckScopeFact($scope, $fact->value);
        $ordinary = $resolver->handle($scope, $plan->retrievalPlan('n1'), $at);
        $this->assertSame($basis !== 'outside_recorded_scope', $ordinary->canSearch());
        $retrieval = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $plan, 15, $at);
        $this->assertSame(RetrievalOutcome::EvidenceFound, $retrieval->outcome);
        $this->assertSame([], $retrieval->candidates);
        $this->assertSame($fact->value, $retrieval->evidenceNeeds[0]['scope_fact']);
        $this->assertArrayNotHasKey('chunk_text', $fact->value);
        Http::assertNothingSent();
    }

    public function test_scope_location_alias_and_singular_or_plural_qualifiers_resolve_without_cross_workspace_guessing(): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        $document->family->update(['name' => 'Equipment Policy']);
        $region = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'North West Region']);
        DB::table('document_applicability_snapshots')->where('document_id', $document->id)->update(['scope' => 'specific']);
        $document->applicabilitySnapshot->locations()->attach($region->id, ['workspace_id' => $workspace->id]);
        $alias = new OrganisationalLocationAlias(['alias' => 'North West']);
        $alias->workspace()->associate($workspace);
        $alias->organisationalLocation()->associate($region);
        $alias->save();

        [$foreign] = $this->scope();
        $foreignLocation = OrganisationalLocation::factory()->for($foreign)->create(['name' => 'Foreign Region']);
        $foreignAlias = new OrganisationalLocationAlias(['alias' => 'Foreign Office']);
        $foreignAlias->workspace()->associate($foreign);
        $foreignAlias->organisationalLocation()->associate($foreignLocation);
        $foreignAlias->save();

        $resolver = app(EligibilityResolver::class);
        $at = CarbonImmutable::parse('2026-09-08');
        foreach (['North West', 'North West Region', 'North West Region site', 'North West Region sites',
            'North West Region teams', 'North West Region homes', 'North West Region offices',
            'North West Region regions'] as $reference) {
            $plan = $this->scopePlan($document, 'Does Equipment Policy cover '.$reference.'?', $reference);
            $fact = $resolver->scopeFact($scope, $plan->retrievalPlan('n1'), $at);
            $this->assertInstanceOf(ApplicabilityScopeFact::class, $fact, $reference);
            $this->assertSame($region->public_id, $fact->value['location_id'], $reference);
            $this->assertSame('direct_match', $fact->value['basis'], $reference);
        }
        foreach (['North Wester', 'Foreign Office'] as $reference) {
            $plan = $this->scopePlan($document, 'Does Equipment Policy cover '.$reference.'?', $reference);
            $result = $resolver->scopeFact($scope, $plan->retrievalPlan('n1'), $at);
            $this->assertSame(RetrievalOutcome::ClarificationRequired, $result->outcome, $reference);
            $this->assertSame('unresolved_location_reference', $result->reason, $reference);
        }
        Http::assertNothingSent();
    }

    public function test_scope_adversarial_framing_is_fixed_and_persists_a_real_snapshot_citation_without_a_provider(): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place']);
        DB::table('document_applicability_snapshots')->where('document_id', $document->id)->update(['scope' => 'specific']);
        $texts = [];
        foreach (['Does Equipment Policy cover Harbour Place?', 'So I do not need to check at Harbour Place, right?'] as $question) {
            $plan = $this->scopePlan($document, $question);
            $retrieval = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $plan, 15, CarbonImmutable::parse('2026-09-08'));
            $input = new GenerationAssemblyInput($question, $retrieval, [], [], $scope, []);
            $answer = app(GenerateGroundedAnswer::class)->handle($input,
                app(GenerationProfileFactory::class)->configuredNeeds());
            $texts[] = $answer->renderedText();
            $this->assertSame('scope-composed-answer-v2', $answer->contract_version);
            $this->assertSame($document->applicabilitySnapshot->id, $answer->scope_facts[0]['snapshot']['snapshot_id']);
            $this->assertCount(0, $answer->evidenceSnapshots);
            $this->assertSame(0, $answer->usage['request_count']);
        }
        $this->assertSame($texts[0], $texts[1]);
        $this->assertStringContainsString('does not include', $texts[1]);
        $this->assertStringContainsString('does not establish what action is safe or required', $texts[1]);
        $this->assertStringNotContainsString('do not need to check', $texts[1]);
        Http::assertNothingSent();
    }

    #[DataProvider('scopeQuestionKinds')]
    public function test_scope_only_conversation_publishes_with_a_zero_cost_local_receipt(string $scopeQuestion): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place']);
        $question = 'Does Equipment Policy cover Harbour Place?';
        $plan = $this->scopePlan($document, $question, scopeQuestion: $scopeQuestion);
        config(['conversation.needs_workspace_ids' => [$workspace->public_id]]);
        Http::fake(function ($request) use ($plan) {
            $this->assertStringEndsWith('/plan-needs', $request->url());

            return Http::response(['contract_version' => 2, 'request_id' => $request->data()['request_id'],
                'plan' => $plan->value, 'classifier_lineage' => $plan->lineage->toArray(),
                'usage' => ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini',
                    'execution' => 'provider_api', 'request_count' => 1, 'retry_count' => 0,
                    'latency_ms' => 1, 'cost_basis' => 'unavailable']]);
        });
        $conversation = app(CreateConversation::class)->handle($workspace, $scope->user);
        $run = app(SubmitConversationMessage::class)->handle($conversation,
            $scope->user, $question, (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame(GenerationRunStatus::Completed, $run->status, json_encode($run->usage));
        $this->assertStringContainsString('recorded scope', $run->assistantMessage->display_text);
        $usage = WorkspaceUsageEvent::where('scope_public_id', $run->public_id)
            ->where('operation_kind', 'generation')->firstOrFail();
        $this->assertSame('application', $usage->provider);
        $this->assertSame('local', $usage->execution);
        $this->assertSame('zero_cost_local', $usage->cost_basis);
        $this->assertSame(0, $usage->request_count);
        $this->assertSame(0.0, (float) $usage->cost_usd);
        $this->assertSame($document->applicabilitySnapshot->id,
            $run->generatedAnswer->scope_facts[0]['snapshot']['snapshot_id']);
        $this->assertSame('applicability_snapshot', $run->generatedAnswer->scope_facts[0]['citation_type']);
        $this->assertDatabaseCount('evidence_snapshots', 0);
        Http::assertSentCount(1);
    }

    public static function scopeQuestionKinds(): array
    {
        return [['membership'], ['breadth']];
    }

    public function test_scope_and_operational_answer_are_isolated_through_generation_and_persistence(): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        $other = $this->document($workspace, $generation, '2026-01-01');
        OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place']);
        $scopeRetrieval = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $this->scopePlan($document), 15, CarbonImmutable::parse('2026-09-08'));
        $chunk = $other->chunks()->firstOrFail();
        $need = $scopeRetrieval->evidenceNeeds[0];
        unset($need['scope_fact']);
        $need = [...$need, 'need_id' => 'n2', 'aspect' => 'How are temporary workers booked?', 'operation' => 'retrieve'];
        $need['claim_under_test'] = ['version' => 'claim-under-test-v1', 'type' => 'named_recipient',
            'relation' => 'submission_destination', 'source' => ['turn_id' => 'user-1', 'quote' => 'Brambleford Office People team']];
        $retrieval = new RetrievalResult(RetrievalOutcome::EvidenceFound,
            [['chunk_id' => $chunk->public_id, 'document_id' => $other->public_id,
                'document_family_id' => $other->family->public_id, 'side' => 'primary',
                'chunk_text' => $chunk->text, 'need_id' => 'n2']],
            evidenceNeeds: [$scopeRetrieval->evidenceNeeds[0], $need]);
        Http::fake(function ($sent) use ($other, $document) {
            $wire = $sent->data();
            $this->assertSame('How are temporary workers booked?', $wire['question']);
            $this->assertSame(['n2'], array_column($wire['evidence_needs'], 'need_id'));
            $this->assertSame('Brambleford Office People team', $wire['evidence_needs'][0]['claim_under_test']['source']['quote']);
            $this->assertSame([$other->id], array_column($wire['evidence'], 'document_id'));
            $this->assertStringNotContainsString($document->public_id, $sent->body());
            $this->assertStringNotContainsString('scope_fact', $sent->body());

            return Http::response(['contract_version' => 2, 'request_id' => $wire['request_id'],
                'status' => 'completed', 'result' => ['contract_version' => 3, 'outcome' => 'answered',
                    'answer_parts' => [['text' => 'Use the authorised booking record.', 'evidence_ids' => ['ev-01']]],
                    'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
                    'entailment_checks' => [['part_index' => 0, 'verdict' => 'supported', 'missing_condition' => null]],
                    'aspect_links' => [['need_id' => 'n2', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []]]]]);
        });
        $input = new GenerationAssemblyInput('Does this scope apply, and do we book temporary workers through the Brambleford Office People team?', $retrieval, [], [], $scope, []);
        $answer = app(GenerateGroundedAnswer::class)->handle($input,
            app(GenerationProfileFactory::class)->configuredNeeds());
        $this->assertCount(1, $answer->scope_facts);
        $this->assertCount(1, $answer->evidenceSnapshots);
        $this->assertSame($other->id, $answer->evidenceSnapshots[0]->document_id);
        $this->assertSame(['n2' => 'supported'], $answer->aspect_results);
        $this->assertStringContainsString('Use the authorised booking record.', $answer->renderedText());
        Http::assertSentCount(1);
    }

    public static function scopeFailures(): array
    {
        return [['foreign_family'], ['foreign_location'], ['future'], ['draft'], ['withdrawn'], ['unsealed'], ['no_snapshot']];
    }

    public static function unavailableScopeRecords(): array
    {
        return [['no_current_version'], ['unsealed_snapshot']];
    }

    #[DataProvider('unavailableScopeRecords')]
    public function test_unavailable_scope_record_publishes_controlled_no_answer(string $failure): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place']);
        if ($failure === 'no_current_version') {
            DB::table('documents')->where('id', $document->id)->update([
                'governance_status' => 'withdrawn', 'withdrawn_at' => '2026-02-01',
            ]);
        } else {
            DB::table('document_applicability_snapshots')->where('document_id', $document->id)
                ->update(['sealed_at' => null]);
        }
        $question = 'Does Equipment Policy cover Harbour Place?';
        $plan = $this->scopePlan($document, $question);
        config(['conversation.needs_workspace_ids' => [$workspace->public_id]]);
        Http::fake(function ($request) use ($plan) {
            $this->assertStringEndsWith('/plan-needs', $request->url());

            return Http::response(['contract_version' => 2, 'request_id' => $request->data()['request_id'],
                'plan' => $plan->value, 'classifier_lineage' => $plan->lineage->toArray(),
                'usage' => ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini',
                    'execution' => 'provider_api', 'request_count' => 1, 'retry_count' => 0,
                    'latency_ms' => 1, 'cost_basis' => 'unavailable']]);
        });
        $conversation = app(CreateConversation::class)->handle($workspace, $scope->user);
        $run = app(SubmitConversationMessage::class)->handle($conversation,
            $scope->user, $question, (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame(GenerationRunStatus::RetrievalNoAnswer, $run->status);
        $this->assertSame(RetrievalOutcome::NoEligibleEvidence, $run->retrievalOutcomeSnapshot->outcome);
        $this->assertSame('scope_record_unavailable', $run->retrievalOutcomeSnapshot->clarification_reason);
        $this->assertSame("I couldn't find enough available evidence to answer that question.", $run->assistantMessage->display_text);
        $this->assertNull($run->generatedAnswer);
        Http::assertSentCount(1);
    }

    #[DataProvider('scopeQuestionKinds')]
    public function test_ambiguous_scope_subject_still_clarifies(string $scopeQuestion): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place']);
        $plan = $this->plan(4, function ($data) use ($document, $scopeQuestion) {
            $data = $this->scopePlan($document, scopeQuestion: $scopeQuestion)->value;
            $data['bindings'][0]['subject_status'] = 'topic';
            $data['bindings'][0]['family_ids'] = [];

            return ['turns' => [['turn_id' => 'user-1', 'text' => 'Does Equipment Policy cover Harbour Place?']], 'plan' => $data];
        });
        $result = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $plan, 15, CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(RetrievalOutcome::ClarificationRequired, $result->outcome);
        $this->assertSame('ambiguous_document_subject', $result->reason);
        $rendered = app(ControlledResponseRenderer::class)->clarification($result->clarificationSource, $result->reason, $result->clarificationQuestion);
        $this->assertSame('Which document or policy do you mean?', $rendered['text']);
        Http::assertNothingSent();
    }

    #[DataProvider('scopeFailures')]
    public function test_scope_enquiry_preserves_access_authority_and_snapshot_prerequisites(string $failure): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        $locationWorkspace = $failure === 'foreign_location' ? Workspace::factory()->create() : $workspace;
        OrganisationalLocation::factory()->create(['workspace_id' => $locationWorkspace->id, 'name' => 'Harbour Place']);
        if ($failure === 'foreign_family') {
            [$foreign, $foreignGeneration] = $this->scope();
            $document = $this->document($foreign, $foreignGeneration, '2026-01-01');
        } elseif ($failure === 'future') {
            DB::table('documents')->where('id', $document->id)->update(['effective_from' => '2027-01-01']);
        } elseif ($failure === 'draft') {
            DB::table('documents')->where('id', $document->id)->update(['governance_status' => 'draft', 'approved_at' => null]);
        } elseif ($failure === 'withdrawn') {
            DB::table('documents')->where('id', $document->id)->update(['governance_status' => 'withdrawn', 'withdrawn_at' => '2026-02-01']);
        } elseif ($failure === 'unsealed') {
            DB::table('document_applicability_snapshots')->where('document_id', $document->id)->update(['sealed_at' => null]);
        } else {
            DB::table('document_applicability_snapshots')->where('document_id', $document->id)->delete();
        }
        foreach (['membership', 'breadth'] as $questionType) {
            $result = app(EligibilityResolver::class)->scopeFact($scope,
                $this->scopePlan($document, scopeQuestion: $questionType)->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-08'));
            $this->assertNotInstanceOf(ApplicabilityScopeFact::class, $result, $questionType);
        }
        Http::assertNothingSent();
    }

    public function test_scope_fact_publication_rechecks_corpus_and_source_identity(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-01-01');
        OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place']);
        $resolver = app(EligibilityResolver::class);
        $fact = $resolver->scopeFact($scope, $this->scopePlan($document)->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-08'));
        $tampered = $fact->value;
        $tampered['result'] = 'does_not_apply';
        try {
            $resolver->recheckScopeFact($scope, $tampered);
            $this->fail('Tampered scope result was accepted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('changed', $error->getMessage());
        }
        $workspace->active_workspace_corpus_generation_id = null;
        $workspace->save();
        $this->expectException(\RuntimeException::class);
        $resolver->recheckScopeFact($scope, $fact->value);
    }

    #[DataProvider('scopeQuestionKinds')]
    public function test_historical_scope_uses_the_selected_versions_snapshot_not_the_current_one(string $scopeQuestion): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $old = $this->document($workspace, $generation, '2026-01-01');
        $new = $this->document($workspace, $generation, '2026-06-01', $old);
        OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Harbour Place']);
        DB::table('document_applicability_snapshots')->where('document_id', $new->id)->update(['scope' => 'specific']);
        $plan = $this->scopePlan($old, scopeQuestion: $scopeQuestion)->retrievalPlan('n1');
        $resolver = app(EligibilityResolver::class);
        $at = CarbonImmutable::parse('2026-09-08');
        $current = $resolver->scopeFact($scope, $plan, $at);
        $historical = new RetrievalPlan($plan->query,
            RetrievalTemporalMode::ValidAtDate, CarbonImmutable::parse('2026-02-01'),
            null, $plan->locationReferences, null, $plan->classifierLineage, $plan->classifierUsage,
            evidenceNeed: $plan->evidenceNeed);
        $past = $resolver->scopeFact($scope, $historical, $at);
        $this->assertSame($new->public_id, $current->value['document_version_id']);
        $this->assertSame($scopeQuestion === 'breadth' ? 'recorded_scope' : 'does_not_apply', $current->value['result']);
        $this->assertSame($old->public_id, $past->value['document_version_id']);
        $this->assertSame($scopeQuestion === 'breadth' ? 'recorded_scope' : 'applies', $past->value['result']);
        if ($scopeQuestion === 'breadth') {
            $this->assertSame([], $current->value['recorded_locations']);
            $this->assertSame([], $past->value['recorded_locations']);
            $this->assertStringContainsString('is universal', $past->renderedText());
            $this->assertStringNotContainsString('changed from', $current->renderedText());
        }
        $resolver->recheckScopeFact($scope, $past->value);
        Http::assertNothingSent();
    }

    public function test_verified_single_version_previous_need_is_not_an_ambiguous_reference(): void
    {
        Http::preventStrayRequests();
        [$workspace, $generation, $scope] = $this->scope();
        $document = $this->document($workspace, $generation, '2026-04-01');
        $plan = $this->plan(0, function ($data) use ($document) {
            foreach ($data['plan']['needs'] as &$need) {
                $need['authority'] = ['kind' => 'previous', 'value' => 'previous', 'precision' => 'named',
                    'source' => $need['question_span']];
            }
            foreach ($data['plan']['bindings'] as &$binding) {
                $binding['subject_status'] = 'matched';
                $binding['family_ids'] = [$document->family->public_id];
            }

            return $data;
        });
        $resolver = app(EligibilityResolver::class);
        foreach (['n1', 'n2'] as $id) {
            $result = $resolver->handle($scope, $plan->retrievalPlan($id), CarbonImmutable::parse('2026-09-06'));
            $this->assertSame('no_previous_version', $result->reason);
            $this->assertFalse($result->canSearch());
        }
        $rendered = app(ControlledResponseRenderer::class)->noAnswer(false, 'no_previous_version');
        $this->assertStringContainsString('no earlier version available', $rendered['text']);
        $this->document($workspace, $generation, '2026-06-01', $document);
        $result = $resolver->handle($scope, $plan->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::EvidenceFound, $result->outcome);
        $this->assertNull($result->reason);
        Http::assertNothingSent();
    }

    public function test_june_pair_is_march_june_while_ordinary_comparison_is_june_september_and_support_remains_current(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $march = $this->document($workspace, $generation, '2026-03-01');
        $june = $this->document($workspace, $generation, '2026-06-15', $march);
        $september = $this->document($workspace, $generation, '2026-09-01', $june);
        $support = $this->document($workspace, $generation, '2026-04-01');
        $resolver = app(EligibilityResolver::class);
        $now = CarbonImmutable::parse('2026-09-06');
        $transition = $resolver->handle($scope, $this->plan(1)->retrievalPlan('n1'), $now);
        $this->assertSame(['primary' => [$june->public_id], 'comparison' => [$march->public_id]], $transition->documentPublicIdsBySide);
        $ordinary = $resolver->handle($scope, $this->plan()->retrievalPlan('n1'), $now);
        $this->assertSame(['primary' => [$september->public_id], 'comparison' => [$june->public_id]], $ordinary->documentPublicIdsBySide);
        $current = $resolver->handle($scope, $this->plan()->retrievalPlan('n2'), $now);
        $this->assertContains($support->public_id, $current->documentPublicIdsBySide['primary']);
        $this->assertContains($september->public_id, $current->documentPublicIdsBySide['primary']);
    }

    public function test_multiple_transitions_require_clarification_and_missing_history_is_not_fabricated(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $march = $this->document($workspace, $generation, '2026-03-01');
        $june = $this->document($workspace, $generation, '2026-06-05', $march);
        $this->document($workspace, $generation, '2026-06-15', $june);
        $result = app(EligibilityResolver::class)->handle($scope, $this->plan(1)->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::ClarificationRequired, $result->outcome);
        [$other, $otherGeneration, $otherScope] = $this->scope();
        $this->document($other, $otherGeneration, '2026-06-12');
        $missing = app(EligibilityResolver::class)->handle($otherScope, $this->plan(1)->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::ComparisonScopeIncomplete, $missing->outcome);
    }

    public function test_named_scope_rejects_foreign_locations_but_accepts_registered_location_qualifiers(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $this->document($workspace, $generation, '2026-01-01');
        OrganisationalLocation::factory()->create(['workspace_id' => Workspace::factory()->create()->id, 'name' => 'Riverside']);
        $resolver = app(EligibilityResolver::class);
        $result = $resolver->handle($scope, $this->plan(4)->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::ClarificationRequired, $result->outcome);
        $riverside = OrganisationalLocation::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Riverside']);
        $plan = $this->plan(4, function ($data) {
            $data['turns'][0]['text'] = 'Which policy applies at Riverside outreach?';
            $data['plan']['needs'][0]['question_span']['quote'] = $data['turns'][0]['text'];
            $data['plan']['needs'][0]['applicability']['source']['quote'] = 'Riverside outreach';

            return $data;
        });
        $result = $resolver->handle($scope, $plan->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::EvidenceFound, $result->outcome);
        $this->assertSame($riverside->public_id, $result->resolvedLocationPublicId);
    }

    public function test_named_location_candidates_survive_the_needs_retrieval_and_renderer_boundary(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        foreach (['House', 'Lodge'] as $descriptor) {
            OrganisationalLocation::factory()->for($workspace)->create([
                'name' => 'Fern '.$descriptor, 'reference_descriptor' => $descriptor,
            ]);
        }
        $plan = $this->plan(4, function ($data) {
            $data['turns'][0]['text'] = 'Which policy applies at Fern?';
            $data['plan']['needs'][0]['question_span']['quote'] = $data['turns'][0]['text'];
            $data['plan']['needs'][0]['applicability']['source']['quote'] = 'Fern';

            return $data;
        });
        Http::preventStrayRequests();
        Http::fake();
        $result = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $plan, 40, CarbonImmutable::parse('2026-09-06'));
        $this->assertSame('ambiguous_location_reference', $result->reason);
        $rendered = (new ControlledResponseRenderer)->clarification($result->clarificationSource, $result->reason, $result->clarificationQuestion);
        $this->assertStringContainsString('Fern House', $rendered['text']);
        $this->assertStringContainsString('Fern Lodge', $rendered['text']);
        Http::assertNothingSent();
    }

    public function test_bound_subject_cannot_borrow_unrelated_history_and_support_stays_available(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $target = $this->document($workspace, $generation, '2026-04-01');
        $other = $this->document($workspace, $generation, '2026-01-01');
        $this->document($workspace, $generation, '2026-06-01', $other);
        $plan = $this->plan(0, function ($data) use ($target) {
            $data['plan']['bindings'][0]['subject_status'] = 'matched';
            $data['plan']['bindings'][0]['family_ids'] = [$target->family->public_id];

            return $data;
        });
        Http::preventStrayRequests();
        $resolver = app(EligibilityResolver::class);
        $result = $resolver->handle($scope, $plan->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::NoEligibleEvidence, $result->outcome);
        $this->assertSame('no_previous_version', $result->reason);
        $this->assertFalse($result->canSearch());
        $support = $resolver->handle($scope, $plan->retrievalPlan('n2'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::EvidenceFound, $support->outcome);
        Http::assertNothingSent();
    }

    public function test_topical_current_scope_remains_broad_but_named_binding_remains_exclusive(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $first = $this->document($workspace, $generation, '2026-01-01');
        $second = $this->document($workspace, $generation, '2026-01-01');
        $resolver = app(EligibilityResolver::class);
        $topic = $this->plan(0, function ($data) {
            $data['plan']['bindings'][1]['subject_status'] = 'topic';
            $data['plan']['bindings'][1]['family_ids'] = [];

            return $data;
        });
        $result = $resolver->handle($scope, $topic->retrievalPlan('n2'), CarbonImmutable::parse('2026-09-06'));
        $this->assertContains($first->public_id, $result->documentPublicIdsBySide['primary']);
        $this->assertContains($second->public_id, $result->documentPublicIdsBySide['primary']);
        $named = $this->plan(0, function ($data) use ($first) {
            $data['plan']['bindings'][1]['subject_status'] = 'matched';
            $data['plan']['bindings'][1]['family_ids'] = [$first->family->public_id];

            return $data;
        });
        $result = $resolver->handle($scope, $named->retrievalPlan('n2'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame([$first->public_id], $result->documentPublicIdsBySide['primary']);
    }

    public function test_external_ownership_is_an_unavailable_need_not_a_location_clarification(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $this->document($workspace, $generation, '2026-01-01');
        $plan = $this->plan(0, function ($data) {
            $data['plan']['bindings'][0]['ownership'] = 'external_tenant';
            $data['plan']['bindings'][0]['ownership_source'] = $data['plan']['needs'][0]['question_span'];

            return $data;
        });
        Http::preventStrayRequests();
        $result = app(EligibilityResolver::class)->handle($scope, $plan->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(RetrievalOutcome::NoEligibleEvidence, $result->outcome);
        $this->assertSame('requested_subject_unavailable', $result->reason);
        Http::assertNothingSent();
    }

    public function test_bound_family_cannot_cross_workspace_even_after_planning(): void
    {
        [$workspace, $generation, $scope] = $this->scope();
        $this->document($workspace, $generation, '2026-01-01');
        [$foreign, $foreignGeneration] = $this->scope();
        $other = $this->document($foreign, $foreignGeneration, '2026-01-01');
        $this->document($foreign, $foreignGeneration, '2026-06-01', $other);
        $plan = $this->plan(0, function ($data) use ($other) {
            $data['plan']['bindings'][0]['subject_status'] = 'matched';
            $data['plan']['bindings'][0]['family_ids'] = [$other->family->public_id];

            return $data;
        });
        $result = app(EligibilityResolver::class)->handle($scope, $plan->retrievalPlan('n1'), CarbonImmutable::parse('2026-09-06'));
        $this->assertFalse($result->canSearch());
    }

    public static function retrievalMutations(): array
    {
        return [['none'], ['corpus_cutover'], ['comparison_removed']];
    }

    #[DataProvider('retrievalMutations')]
    public function test_real_retrieval_action_dispatches_each_need_and_rechecks_final_authority(string $mutation): void
    {
        [$workspace, $generation, $scope, $policy] = $this->scope(true);
        $old = $this->document($workspace, $generation, '2026-03-01');
        $new = $this->document($workspace, $generation, '2026-06-15', $old);
        $support = $this->document($workspace, $generation, '2026-04-01');
        $generation->load(['embeddingSpaceGeneration.embeddingProfile', 'sparseSpaceGeneration']);
        $searchScopes = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($workspace, $generation, $policy, $old, $mutation, &$searchScopes) {
            $body = $request->data();
            if (str_ends_with($request->url(), '/search')) {
                $this->assertArrayHasKey('hybrid_configuration', $body);
                $searchScopes[] = $body['scopes'];
                $candidates = [];
                foreach ($body['scopes'] as $lane) {
                    foreach ($lane['eligible_document_ids'] as $documentId) {
                        $document = Document::query()->where('public_id', $documentId)->firstOrFail();
                        $chunk = $document->chunks()->firstOrFail();
                        $candidates[] = ['chunk_id' => $chunk->public_id, 'document_id' => $documentId,
                            'workspace_corpus_generation_id' => $generation->public_id,
                            'embedding_space_generation_id' => $generation->embeddingSpaceGeneration->public_id,
                            'sparse_space_generation_id' => $generation->sparseSpaceGeneration->public_id,
                            'score' => 0.04, 'rank' => count($candidates) + 1, 'retrieval_method' => 'hybrid', 'side' => $lane['side']];
                    }
                }

                return Http::response(['request_id' => $body['request_id'], 'candidates' => $candidates, 'diagnostics' => [],
                    'lineage' => ['embedding_profile_fingerprint' => $policy->embedding_profile_fingerprint,
                        'sparse_profile_fingerprint' => $policy->sparse_profile_fingerprint,
                        'sparse_space_generation_id' => $generation->sparseSpaceGeneration->public_id,
                        'fusion_strategy' => $policy->fusion_strategy, 'fusion_version' => $policy->fusion_version,
                        'rrf_k' => $policy->rrf_k, 'configuration_version' => $policy->version]]);
            }
            $this->assertStringEndsWith('/rerank', $request->url());
            if (count($searchScopes) === 2) {
                if ($mutation === 'corpus_cutover') {
                    $workspace->active_workspace_corpus_generation_id = null;
                    $workspace->save();
                } elseif ($mutation === 'comparison_removed') {
                    $old->status = DocumentStatus::Deleting;
                    $old->save();
                }
            }
            $ranks = [];
            $reranked = [];
            foreach ($body['candidates'] as $candidate) {
                $this->assertNotEmpty($candidate['document_title']);
                $side = $candidate['side'];
                $ranks[$side] = ($ranks[$side] ?? 0) + 1;
                $reranked[] = ['chunk_id' => $candidate['chunk_id'], 'side' => $side,
                    'score' => 0.95, 'rank' => $ranks[$side]];
            }

            return Http::response(['request_id' => $body['request_id'], 'profile' => $body['profile'],
                'candidates' => $reranked,
                'provider_input_tokens' => 17, 'provider_attempt_count' => 1, 'provider_retry_count' => 0,
                'rate_limit_event_count' => 0, 'retry_delays' => [], 'first_provider_attempt_at' => '2026-09-06T12:00:00Z',
                'final_provider_success_at' => '2026-09-06T12:00:01Z', 'provider_retry_elapsed_seconds' => 0]);
        });
        $observations = [];
        $result = app(RetrieveWorkspaceEvidence::class)->handleNeeds($scope, $this->plan(), 10, CarbonImmutable::parse('2026-09-06'),
            function ($stage, $value) use (&$observations) {
                $observations[$stage] = $value;
            });
        if ($mutation === 'corpus_cutover') {
            $this->assertSame(RetrievalOutcome::RetrievalFailed, $result->outcome);
            $this->assertSame('active_corpus_changed', $result->reason);
            $this->assertSame([], $result->candidates);

            return;
        }
        $this->assertSame(RetrievalOutcome::EvidenceFound, $result->outcome, json_encode($observations));
        $this->assertCount(2, $searchScopes);
        $this->assertNotContains($support->public_id, $searchScopes[0][0]['eligible_document_ids']);
        $this->assertContains($support->public_id, $searchScopes[1][0]['eligible_document_ids']);
        $this->assertContains($support->public_id, array_column($result->candidates, 'document_id'));
        if ($mutation === 'comparison_removed') {
            $this->assertNotContains($old->public_id, array_column($result->candidates, 'document_id'));
            $this->assertNotContains('n1', array_column($result->candidates, 'need_id'));
        } else {
            $this->assertContains($old->public_id, array_column($result->candidates, 'document_id'));
            $this->assertContains($new->public_id, array_column($result->candidates, 'document_id'));
        }
        $this->assertLessThanOrEqual(5, count($result->candidates));
        $this->assertCount(1, array_filter($result->usage, fn ($u) => $u['stage'] === 'planner'));
        $this->assertArrayNotHasKey('supports_need_id', $result->evidenceNeeds[1]);
        Http::assertSentCount(4);
    }
}
