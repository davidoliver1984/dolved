<?php

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Generation\AnswerCurrentPolicyMessage;
use App\Enums\MessageRole;
use App\Enums\WorkspaceRole;
use App\Exceptions\GenerationException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\EvidenceThresholdPolicy;
use App\Models\GeneratedAnswer;
use App\Models\IngestionEventClaim;
use App\Models\Message;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Models\WorkspaceMembership;
use App\Services\Generation\GenerationProfileFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentPolicyBackendTest extends TestCase
{
    use RefreshDatabase;

    private array $calls = [];

    private array $payloads = [];

    public static function outcomes(): array
    {
        return array_map(fn ($v) => [$v], ['completed', 'capacity', 'invalid_output', 'incomplete_output', 'unavailable', 'source_capacity', 'partial_unavailable', 'partial_timeout']);
    }

    #[DataProvider('outcomes')]
    public function test_real_services_pool_compose_and_save_partial_outcomes_without_selector_or_reviewer(string $mode): void
    {
        [$message, $pin, $document] = $this->fixture($mode);
        $saved = app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
        $fresh = $saved->fresh('generatedAnswer.answerParts.evidenceSnapshots');
        $this->assertSame($saved->display_text, $fresh->display_text);
        $this->assertStringContainsString('what changed in the earlier edition?', $fresh->display_text);
        $this->assertStringContainsString('current policy only', $fresh->display_text);
        $this->assertStringContainsString('Which arrangement do you mean?', $fresh->display_text);
        $this->assertSame(1, count(array_filter($this->calls, fn ($p) => str_ends_with($p, '/plan-needs'))));
        $this->assertSame(2, count(array_filter($this->calls, fn ($p) => str_ends_with($p, '/search'))), json_encode($fresh->generatedAnswer->temporal_context['current_policy']['retrieval']));
        $gen = count(array_filter($this->calls, fn ($p) => str_ends_with($p, '/current-policy-answer')));
        $this->assertSame(in_array($mode, ['unavailable', 'source_capacity'], true) ? 0 : 1, $gen);
        foreach ($this->calls as $path) {
            $this->assertDoesNotMatchRegularExpression('/coverage-selection|compare-family|answer-needs|\/stream$/', $path);
        }
        if (in_array($mode, ['completed', 'partial_unavailable', 'partial_timeout'], true)) {
            $payload = $this->payloads[0];
            $this->assertCount(5, $payload['sources']);
            $this->assertSame($mode === 'completed' ? ['n1', 'n2'] : ['n1'], array_column($payload['sources'][0]['associations'], 'need_id'));
            $this->assertSame([$document->public_id], array_values(array_unique(array_column($payload['sources'], 'document_id'))));
            $this->assertStringNotContainsString('earlier edition', implode(' ', $payload['admitted_requests']));
            $this->assertStringContainsString('Wear gloves', $fresh->display_text);
            $this->assertCount(1, $fresh->generatedAnswer->evidenceSnapshots);
            $this->assertSame($payload['sources'][0]['text'], $fresh->generatedAnswer->evidenceSnapshots[0]->cited_text_verbatim);
            $this->assertSame([], $fresh->generatedAnswer->aspect_links ?? []);
        } else {
            $this->assertCount(0, $fresh->generatedAnswer->answerParts);
            $this->assertStringNotContainsString('Wear gloves.', $fresh->display_text);
        }
        $this->assertSame('current-policy-answer-v1', $fresh->generatedAnswer->contract_version);
        $this->assertNotEmpty($fresh->generatedAnswer->temporal_context['current_policy']['dispositions']);
    }

    public static function violations(): array
    {
        return [['invalid_citation'], ['changed_source'], ['membership_revoked']];
    }

    #[DataProvider('violations')]
    public function test_integrity_membership_and_citation_failures_save_no_partial_message(string $mode): void
    {
        [$message, $pin] = $this->fixture($mode);
        try {
            app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
            $this->fail('Violation was accepted.');
        } catch (\Throwable $error) {
            $this->assertNotInstanceOf(AssertionFailedError::class, $error);
        }
        $this->assertSame(1, Message::count());
        $this->assertSame(0, GeneratedAnswer::count());
        $this->assertCount(1, $this->payloads);
    }

    public function test_default_off_refuses_backend_execution_before_any_call(): void
    {
        [$message, $pin] = $this->fixture();
        config(['conversation.current_policy_workspace_ids' => []]);
        try {
            app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16'));
            $this->fail('Disabled profile executed.');
        } catch (GenerationException $error) {
            $this->assertSame([], $this->calls);
        }
    }

    public function test_three_lanes_fifteen_physical_passages_and_cross_need_citation_are_allowed(): void
    {
        [$message, $pin] = $this->fixture('fifteen');
        $saved = app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
        $this->assertCount(15, $this->payloads[0]['sources']);
        $this->assertSame(['n1', 'n2', 'n3'], array_values(array_unique(array_map(fn ($s) => $s['associations'][0]['need_id'], $this->payloads[0]['sources']))));
        $this->assertSame('ev-11', $saved->generatedAnswer->evidenceSnapshots[0]->evidence_handle);
        $this->assertCount(1, $this->payloads);
        try {
            app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
            $this->fail('Saved message was redispatched.');
        } catch (GenerationException $error) {
            $this->assertCount(1, $this->payloads);
        }
    }

    public function test_current_scope_fact_is_composed_and_persisted_without_becoming_operational_evidence(): void
    {
        [$message, $pin] = $this->fixture('scope_fact');
        $saved = app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
        $this->assertCount(1, $saved->generatedAnswer->scope_facts);
        $this->assertStringContainsString('recorded scope', $saved->display_text);
        $this->assertStringContainsString('does not establish what action is safe', $saved->display_text);
        $this->assertCount(2, $this->payloads[0]['admitted_requests']);
        $this->assertCount(1, $this->payloads);
    }

    public function test_transaction_rolls_back_final_message_when_answer_persistence_fails(): void
    {
        [$message, $pin] = $this->fixture();
        Event::listen('eloquent.creating: '.GeneratedAnswer::class, fn () => throw new \RuntimeException('controlled persistence fault'));
        try {
            app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
            $this->fail('Persistence fault did not propagate.');
        } catch (\RuntimeException $error) {
            $this->assertSame('controlled persistence fault', $error->getMessage());
        }
        $this->assertSame(1, Message::count());
        $this->assertSame(0, GeneratedAnswer::count());
        $this->assertCount(1, $this->payloads);
    }

    public function test_capacity_does_not_relabel_an_independently_unavailable_part(): void
    {
        [$message, $pin] = $this->fixture('capacity_and_unavailable');
        $saved = app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
        $rows = $saved->generatedAnswer->temporal_context['current_policy']['dispositions'];
        $this->assertSame(['admitted', 'unavailable'], array_column(array_values(array_filter($rows, fn ($d) => $d['need_id'] === 'n2')), 'status'));
        $this->assertSame(['partial_scope', 'capacity'], array_column(array_values(array_filter($rows, fn ($d) => $d['need_id'] === 'n1')), 'status'));
        $this->assertCount(0, $this->payloads);
    }

    public function test_unclassified_retrieval_failure_never_becomes_partial_generation(): void
    {
        [$message, $pin] = $this->fixture('retrieval_auth_failure');
        try {
            app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
            $this->fail('Authentication failure was consumed.');
        } catch (GenerationException $error) {
            $this->assertCount(0, $this->payloads);
            $this->assertSame(1, Message::count());
        }
    }

    public static function terminalScopes(): array
    {
        return [['all_history', 'no_answer'], ['all_unclear', 'clarification']];
    }

    #[DataProvider('terminalScopes')]
    public function test_wholly_declined_or_ambiguous_requests_are_saved_without_retrieval_or_generation(string $mode, string $kind): void
    {
        [$message, $pin] = $this->fixture($mode);
        $saved = app(AnswerCurrentPolicyMessage::class)->handle($message, $pin, CarbonImmutable::parse('2026-11-16T12:00:00Z'));
        $this->assertSame($kind, $saved->kind->value);
        $this->assertCount(1, $this->calls);
        $this->assertCount(0, $this->payloads);
        $this->assertStringContainsString('Who checks the equipment?', $saved->display_text);
        $this->assertStringNotContainsString('documents lack', $saved->display_text);
    }

    private function fixture(string $mode = 'completed'): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => WorkspaceRole::Owner]);
        $dense = EmbeddingProfile::factory()->create();
        $space = EmbeddingSpaceGeneration::factory()->available()->create(['embedding_profile_id' => $dense->id, 'dimensions' => $dense->dimensions]);
        $sparse = SparseEmbeddingProfile::factory()->create();
        $sparseSpace = SparseSpaceGeneration::factory()->available()->create(['sparse_embedding_profile_id' => $sparse->id, 'embedding_space_generation_id' => $space->id]);
        $policy = EvidenceThresholdPolicy::factory()->active()->create(['embedding_profile_fingerprint' => $dense->fingerprint,
            'sparse_profile_fingerprint' => $sparse->fingerprint, 'final_evidence_k' => 2]);
        $generation = WorkspaceCorpusGeneration::factory()->active()->create(['workspace_id' => $workspace->id,
            'embedding_space_generation_id' => $space->id, 'sparse_space_generation_id' => $sparseSpace->id, 'verified_at' => now()]);
        $workspace->active_workspace_corpus_generation_id = $generation->id;
        $workspace->save();
        $old = Document::factory()->indexed()->approved()->create(['workspace_id' => $workspace->id, 'effective_from' => '2025-01-01', 'approved_at' => '2025-01-01']);
        $document = Document::factory()->indexed()->approved()->create(['workspace_id' => $workspace->id, 'document_family_id' => $old->document_family_id,
            'predecessor_document_id' => $old->id, 'effective_from' => '2026-01-01', 'approved_at' => '2026-01-01']);
        $foreign = Document::factory()->indexed()->approved()->create(['effective_from' => '2026-01-01', 'approved_at' => '2026-01-01']);
        $chunks = [];
        foreach ([$document, $old, $foreign] as $doc) {
            $claim = IngestionEventClaim::factory()->create(['workspace_id' => $doc->workspace_id, 'document_id' => $doc->id]);
            for ($i = 1; $i <= ($doc->id === $document->id ? ($mode === 'fifteen' ? 15 : 7) : 1); $i++) {
                $text = in_array($mode, ['source_capacity', 'capacity_and_unavailable'], true) ? str_repeat('a', 12001) : 'Wear gloves. Inspect weekly. Passage '.$i;
                $chunk = DocumentChunk::factory()->create(['workspace_id' => $doc->workspace_id, 'document_id' => $doc->id,
                    'ingestion_event_claim_id' => $claim->id, 'ordinal' => $i, 'text' => $text, 'content_digest' => hash('sha256', $text)]);
                if ($doc->workspace_id === $workspace->id) {
                    WorkspaceCorpusGenerationChunk::factory()->create(['workspace_id' => $workspace->id, 'workspace_corpus_generation_id' => $generation->id, 'document_chunk_id' => $chunk->id]);
                }
                $chunks[] = $chunk;
            }
        }
        $response = json_decode(file_get_contents('/contracts/http/current-policy-needs/v1/response.example.json'), true, flags: JSON_THROW_ON_ERROR);
        $template = $response['plan']['needs'][0];
        foreach (['question_span', 'authority'] as $field) {
            if ($field === 'question_span') {
                $response['plan']['needs'][0][$field]['turn_id'] = 'user-1';
            } else {
                $response['plan']['needs'][0][$field]['source']['turn_id'] = 'user-1';
            }
        }
        foreach (['current_spans', 'history_spans'] as $field) {
            $response['plan']['needs'][0][$field][0]['turn_id'] = 'user-1';
        }
        $second = [...$template, 'need_id' => 'n2', 'question_span' => ['turn_id' => 'user-1', 'quote' => 'Who checks the equipment?'],
            'operation' => 'retrieve', 'authority' => ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null],
            'requested_information' => 'current_policy', 'current_spans' => [], 'history_spans' => []];
        $response['plan']['needs'][] = $second;
        $response['plan']['needs'][] = [...$second, 'need_id' => 'n3', 'question_span' => ['turn_id' => 'user-1', 'quote' => 'Which arrangement do you mean?'], 'requested_information' => 'unclear'];
        $response['plan']['bindings'] = array_map(fn ($id) => ['need_id' => $id, 'subject_status' => 'topic', 'family_ids' => [], 'ownership' => 'authorised_workspace', 'ownership_source' => null], ['n1', 'n2', 'n3']);
        if (in_array($mode, ['all_history', 'all_unclear'], true)) {
            foreach ($response['plan']['needs'] as &$need) {
                $need['requested_information'] = $mode === 'all_history' ? 'history' : 'unclear';
                $need['current_spans'] = $need['history_spans'] = [];
            }
            unset($need);
        }
        if ($mode === 'fifteen') {
            $response['plan']['needs'][2]['requested_information'] = 'current_policy';
        }
        if ($mode === 'scope_fact') {
            $response['plan']['needs'][2]['requested_information'] = 'current_policy';
            $response['plan']['needs'][2]['operation'] = 'scope_enquiry';
            $response['plan']['needs'][2]['scope_question'] = 'breadth';
            $response['plan']['bindings'][2]['subject_status'] = 'matched';
            $response['plan']['bindings'][2]['family_ids'] = [$document->family->public_id];
        }
        $question = implode(' ', array_column(array_column($response['plan']['needs'], 'question_span'), 'quote'));
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $message = Message::create(['public_id' => (string) Str::uuid(), 'conversation_id' => $conversation->id, 'workspace_id' => $workspace->id,
            'ordinal' => 1, 'role' => MessageRole::User, 'display_text' => $question, 'created_by_user_id' => $user->id]);
        config(['conversation.current_policy_workspace_ids' => [$workspace->public_id]]);
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($response, $chunks, $generation, $space, $sparseSpace, $policy, $mode, $document, $workspace) {
            $this->calls[] = $r->url();
            $b = $r->data();
            if (str_ends_with($r->url(), '/plan-needs')) {
                return Http::response([...$response, 'request_id' => $b['request_id']]);
            }
            if (str_ends_with($r->url(), '/search')) {
                foreach ($b['scopes'] as $lane) {
                    $this->assertSame('primary', $lane['side']);
                    $this->assertSame([$document->public_id], $lane['eligible_document_ids']);
                }
                $empty = $mode === 'unavailable' || (in_array($mode, ['partial_unavailable', 'capacity_and_unavailable'], true) && count(array_filter($this->calls, fn ($p) => str_ends_with($p, '/search'))) === 2);
                if ($mode === 'fifteen') {
                    $chunks = array_slice($chunks, (count(array_filter($this->calls, fn ($p) => str_ends_with($p, '/search'))) - 1) * 5, 5);
                }

                return Http::response(['request_id' => $b['request_id'], 'candidates' => $empty ? [] : array_map(fn ($chunk, $i) => [
                    'chunk_id' => $chunk->public_id, 'document_id' => $chunk->document->public_id, 'workspace_corpus_generation_id' => $generation->public_id,
                    'embedding_space_generation_id' => $space->public_id, 'sparse_space_generation_id' => $sparseSpace->public_id,
                    'score' => .9, 'rank' => $i + 1, 'retrieval_method' => 'hybrid', 'side' => 'primary'], $chunks, array_keys($chunks)),
                    'diagnostics' => [], 'lineage' => ['embedding_profile_fingerprint' => $policy->embedding_profile_fingerprint,
                        'sparse_profile_fingerprint' => $policy->sparse_profile_fingerprint, 'sparse_space_generation_id' => $sparseSpace->public_id,
                        'fusion_strategy' => $policy->fusion_strategy, 'fusion_version' => $policy->fusion_version, 'rrf_k' => $policy->rrf_k, 'configuration_version' => $policy->version]]);
            }
            if (str_ends_with($r->url(), '/rerank')) {
                if ($mode === 'retrieval_auth_failure') {
                    return Http::response([], 401);
                }
                if ($mode === 'partial_timeout' && count(array_filter($this->calls, fn ($p) => str_ends_with($p, '/rerank'))) === 2) {
                    return Http::response(['detail' => ['failure' => ['stage' => 'reranker', 'execution' => 'provider_api',
                        'provider' => 'voyage', 'model' => 'test', 'category' => 'timeout', 'http_status' => null,
                        'retry_count' => 2, 'request_count' => 3, 'latency_ms' => 100, 'usage' => [],
                        'downstream_request_attempted' => true, 'candidate_lineage_produced' => true]]], 503);
                }

                return Http::response(['request_id' => $b['request_id'], 'profile' => $b['profile'],
                    'candidates' => array_map(fn ($c, $i) => ['chunk_id' => $c['chunk_id'], 'side' => 'primary', 'score' => $i === 6 ? .1 : .95, 'rank' => $i + 1], $b['candidates'], array_keys($b['candidates'])),
                    'provider_input_tokens' => 17, 'provider_attempt_count' => 1, 'provider_retry_count' => 0, 'rate_limit_event_count' => 0,
                    'retry_delays' => [], 'first_provider_attempt_at' => '2026-11-16T12:00:00Z', 'final_provider_success_at' => '2026-11-16T12:00:01Z', 'provider_retry_elapsed_seconds' => 0]);
            }
            $this->assertStringEndsWith('/current-policy-answer', $r->url());
            $this->payloads[] = $b;
            if ($mode === 'changed_source') {
                DB::table('document_chunks')->where('id', $chunks[0]->id)->update(['text' => 'changed']);
            }
            if ($mode === 'membership_revoked') {
                WorkspaceMembership::where('workspace_id', $workspace->id)->delete();
            }
            $base = ['contract_version' => 'current-policy-answer-v1', 'request_id' => $b['request_id'],
                'identity' => ['provider' => config('generation.provider'), 'model' => config('generation.model'),
                    'prompt' => 'current-policy-pool-generation-v1', 'adapter' => 'current-policy-responses-v1',
                    'reasoning_effort' => config('generation.quality_affecting_configuration.reasoning_effort'), 'max_output_tokens' => 4096, 'max_retries' => 0],
                'usage' => ['request_count' => $mode === 'capacity' ? 0 : 1, 'retry_count' => 0]];
            if (in_array($mode, ['invalid_output', 'incomplete_output'], true)) {
                return Http::response([...$base, 'status' => 'failed', 'reason' => $mode]);
            }
            if ($mode === 'capacity') {
                return Http::response([...$base, 'status' => 'capacity', 'request_units' => 120001]);
            }

            return Http::response([...$base, 'status' => 'completed', 'result' => ['outcome' => 'qualified',
                'answer_parts' => [['text' => 'Wear gloves.', 'evidence_ids' => [$mode === 'invalid_citation' ? 'ev-99' : ($mode === 'fifteen' ? 'ev-11' : 'ev-01')]]],
                'unsupported_aspects' => ['The checker role is not established.']]]);
        });

        return [$message, app(GenerationProfileFactory::class)->currentPolicyPin(), $document];
    }
}
