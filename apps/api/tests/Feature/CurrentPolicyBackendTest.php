<?php

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Actions\Generation\AnswerCurrentPolicyMessage;
use App\Enums\DocumentStatus;
use App\Enums\MessageRole;
use App\Enums\WorkspaceRole;
use App\Exceptions\GenerationException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\EvidenceThresholdPolicy;
use App\Models\GeneratedAnswer;
use App\Models\GenerationRun;
use App\Models\IngestionEventClaim;
use App\Models\Message;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceUsageEvent;
use App\Services\Generation\GenerationProfileFactory;
use App\Support\Usage\RecordWorkspaceUsage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentPolicyBackendTest extends TestCase
{
    use RefreshDatabase;

    private array $calls = [];

    private array $payloads = [];

    private ?string $producedReceipt = null;

    public function test_s6_python_canonical_receipt_completes_and_persists_without_extra_generation(): void
    {
        Queue::fake();
        $this->producedReceipt = 'smoke03_s6';
        [$initial] = $this->fixture('current_only');
        $conversation = $initial->conversation;
        $user = $initial->createdBy;
        $question = $initial->display_text;
        $initial->delete();
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, $question, (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame('completed', $run->status->value);
        $this->assertCount(1, $this->payloads);
        $expected = json_decode(file_get_contents('/contracts/http/current-policy-answer/v1/smoke03-s6-canonical-receipt.json'), true, flags: JSON_THROW_ON_ERROR);
        $answer = $run->generatedAnswer->fresh();
        $this->assertSame($expected['result'], $answer->temporal_context['current_policy']['generation']['result']);
        $this->assertSame($expected['usage'], $answer->temporal_context['current_policy']['generation']['usage']);
        $this->assertSame(implode("\n\n", array_column($expected['result']['answer_parts'], 'text')), $answer->renderedText());
        $this->assertCount(4, $answer->evidenceSnapshots);
        $this->assertSame(1, $run->deliveryEvents()->where('event_type', 'answer_completed')->count());
        $this->assertSame(1, Message::where('role', 'assistant')->count());
        $usage = WorkspaceUsageEvent::where('scope_public_id', $run->public_id)->where('operation_kind', 'generation')->sole();
        $this->assertEqualsWithDelta($expected['usage']['cost_usd'], (float) $usage->cost_usd, 0.00000001);
    }

    public static function producedReceiptCases(): array
    {
        return [['supported', 'completed', 'answered'], ['limitations', 'completed', 'qualified'],
            ['no_support', 'retrieval_no_answer', 'insufficient_evidence'],
            ['invalid_citation', 'retrieval_no_answer', 'insufficient_evidence'],
            ['empty', 'retrieval_no_answer', 'insufficient_evidence'], ['malformed_usage', 'failed', null]];
    }

    #[DataProvider('producedReceiptCases')]
    public function test_python_adapter_receipt_completes_real_transaction_and_bad_usage_rolls_it_back(string $receipt, string $status, ?string $outcome): void
    {
        Queue::fake();
        $this->producedReceipt = $receipt;
        [$initial] = $this->fixture('current_only');
        $conversation = $initial->conversation;
        $user = $initial->createdBy;
        $question = $initial->display_text;
        $initial->delete();
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, $question, (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame($status, $run->status->value, json_encode($run->usage));
        if ($receipt === 'malformed_usage') {
            $this->assertSame('internal_failure', $run->failure_code->value);
            $this->assertSame('The workspace usage entry is invalid.', $run->usage['internal_failure']['message']);
            $this->assertDatabaseCount('generated_answers', 0);
            $this->assertDatabaseCount('evidence_snapshots', 0);
            $this->assertDatabaseCount('workspace_usage_events', 0);
            $this->assertSame(0, Message::where('role', 'assistant')->count());
            $this->assertSame(0, $run->deliveryEvents()->where('event_type', 'answer_completed')->count());

            return;
        }
        $answer = $run->generatedAnswer->fresh();
        $this->assertSame($outcome, $answer->outcome->value);
        $this->assertSame(1, Message::where('role', 'assistant')->count());
        $this->assertSame(1, $run->deliveryEvents()->where('event_type', 'answer_completed')->count());
        $this->assertSame($run->assistantMessage->display_text, $answer->renderedText());
        $usage = WorkspaceUsageEvent::where('scope_public_id', $run->public_id)->where('operation_kind', 'generation')->sole();
        $this->assertSame('estimated', $usage->cost_basis);
        $this->assertSame('openai-gpt-5-mini-pricing-2026-08-19', $usage->pricing_snapshot);
        $this->assertEqualsWithDelta(0.00008275, (float) $usage->cost_usd, 0.00000001);
        if (in_array($receipt, ['supported', 'limitations'], true)) {
            $this->assertCount(1, $answer->evidenceSnapshots);
            $this->assertStringContainsString('Wear gloves.', $answer->renderedText());
        }
        if (in_array($receipt, ['limitations', 'no_support'], true)) {
            // Python accepted the model's answered label, but persistence derives
            // qualified/no-answer and never hides the actual unsupported portion.
            $this->assertSame('answered', $answer->temporal_context['current_policy']['generation']['result']['outcome']);
            $this->assertStringContainsString('The checker role is not established.', $answer->renderedText());
        }
        if (in_array($receipt, ['invalid_citation', 'empty'], true)) {
            $this->assertCount(0, $answer->evidenceSnapshots);
            $this->assertStringContainsString('could not be generated safely', $answer->renderedText());
        }
    }

    public static function deliveryOutcomes(): array
    {
        return [...self::outcomes(), ['scope_fact'], ['all_history'], ['all_unclear']];
    }

    public function test_retained_s1_receipts_against_actual_recorder_contract(): void
    {
        $rows = json_decode(file_get_contents(base_path('tests/Fixtures/current-policy-smoke01-usage.json')), true, flags: JSON_THROW_ON_ERROR)['receipts'];
        $recorder = app(RecordWorkspaceUsage::class);
        $validate = new \ReflectionMethod($recorder, 'validate');
        foreach ($rows as $stage => $usage) {
            if ($stage === 'interpretation_checker') {
                // This genuinely retained nested receipt has no stage/execution;
                // production accounts it inside the planner aggregate, not twice.
                $this->assertArrayNotHasKey('stage', $usage);
                $this->assertArrayNotHasKey('execution', $usage);
                try {
                    $validate->invoke($recorder, $usage);
                    $this->fail('Nested checker receipt accepted as a standalone ledger entry.');
                } catch (\InvalidArgumentException $error) {
                    $this->assertSame('The workspace usage entry is invalid.', $error->getMessage());
                }
                $this->assertGreaterThanOrEqual($usage['request_count'], $rows['planner']['request_count']);

                continue;
            }
            if ($stage === 'generation') {
                $usage = (new \ReflectionMethod(OrchestrateConversationRun::class, 'normalisedUsage'))
                    ->invoke(app(OrchestrateConversationRun::class), $usage, 'generation', $usage['provider'], $usage['model']);
                try {
                    $validate->invoke($recorder, $usage);
                    $this->fail('Historical malformed generation usage accepted.');
                } catch (\InvalidArgumentException $error) {
                    $this->assertSame('The workspace usage entry is invalid.', $error->getMessage());
                }

                continue;
            }
            $validate->invoke($recorder, $usage);
            $this->addToAssertionCount(1);
        }
    }

    public function test_optional_generation_transport_failure_keeps_liability_and_completes_honest_disclosure(): void
    {
        Queue::fake();
        $this->producedReceipt = 'service_transport';
        [$initial] = $this->fixture('current_only');
        $conversation = $initial->conversation;
        $user = $initial->createdBy;
        $question = $initial->display_text;
        $initial->delete();
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, $question, (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame('retrieval_no_answer', $run->status->value);
        $this->assertStringContainsString('could not be generated safely', $run->assistantMessage->display_text);
        $receipt = $run->usage['current_policy']['generation'];
        $this->assertSame('service_transport', $receipt['reason']);
        $this->assertSame(1, $receipt['usage']['request_count']);
        $this->assertSame(0, $receipt['usage']['retry_count']);
        $usage = WorkspaceUsageEvent::where('scope_public_id', $run->public_id)->where('operation_kind', 'generation')->sole();
        $this->assertSame('unavailable', $usage->cost_basis);
        $this->assertNull($usage->cost_usd);
        $this->assertSame(1, count($this->payloads));
    }

    #[DataProvider('deliveryOutcomes')]
    public function test_authenticated_submission_claim_final_event_and_reload_use_one_saved_answer(string $mode): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow('2026-11-16T12:00:00Z');
        [$initial, $pin] = $this->fixture($mode);
        $user = $initial->createdBy;
        $conversation = $initial->conversation;
        $question = $initial->display_text;
        $initial->delete();
        $path = '/api/workspaces/'.$conversation->workspace->public_id.'/conversations/'.$conversation->public_id;
        $body = ['message' => $question, 'idempotency_key' => (string) Str::uuid()];
        $id = $this->actingAs($user)->postJson($path.'/messages', $body)->assertAccepted()->json('data.run_id');
        $this->postJson($path.'/messages', $body)->assertAccepted()->assertJsonPath('data.run_id', $id);
        $run = GenerationRun::where('public_id', $id)->firstOrFail();
        $this->assertSame($pin, $run->needs_profile);
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertNotSame('failed', $run->status->value, json_encode($run->usage));
        $count = count($this->calls);
        app(OrchestrateConversationRun::class)->handle($run->id);
        $this->assertCount($count, $this->calls);
        $this->assertSame(1, Message::where('role', 'assistant')->count());
        $saved = $run->assistantMessage;
        $this->assertSame($saved->display_text, $run->generatedAnswer->fresh()->renderedText());
        $data = $this->getJson($path)->assertOk()->json('data');
        $answer = $data['runs'][0]['answer'];
        $this->assertSame('saved-message-v1', $answer['presentation']);
        $this->assertSame($saved->display_text, $answer['text']);
        $this->assertArrayNotHasKey('parts', $answer);
        $this->assertArrayNotHasKey('unsupported_aspects', $answer);
        if ($mode === 'location') {
            $this->assertSame(1, substr_count($saved->display_text, 'General guidance does not establish coverage of that location.'));
            $this->assertStringContainsString('Cedar outreach', $saved->display_text);
        }
        $event = $run->deliveryEvents()->where('event_type', $mode === 'all_unclear' ? 'clarification_required' : 'answer_completed')->firstOrFail();
        $this->assertSame($saved->display_text, $event->safe_payload['text']);
        $this->assertSame(array_column($answer['citations'], 'id'), array_column($event->safe_payload['citations'], 'id'));
        $this->assertFalse($run->deliveryEvents()->where('event_type', 'answer_part_accepted_for_display')->exists());
        $this->assertSame($data, $this->getJson($path)->assertOk()->json('data'));
        $this->assertCount($count, $this->calls);
        if ($mode === 'completed') {
            $snapshot = $run->generatedAnswer->answerParts->first()->evidenceSnapshots->first();
            $snapshot->document->forceFill(['status' => DocumentStatus::Deleted])->save();
            $removed = $this->getJson($path)->assertOk()->json('data.runs.0.answer');
            $this->assertSame($saved->display_text, $removed['text']);
            $this->assertSame('removed', $removed['citations'][0]['source_state']);
            $this->assertNull($removed['citations'][0]['source_route']);
            $this->assertSame($snapshot->public_id, $removed['citations'][0]['id']);
            $this->assertCount($count, $this->calls);
        }
        WorkspaceMembership::where('workspace_id', $conversation->workspace_id)->delete();
        $this->getJson($path)->assertNotFound();
        CarbonImmutable::setTestNow();
    }

    public static function outcomes(): array
    {
        return array_map(fn ($v) => [$v], ['completed', 'capacity', 'invalid_output', 'incomplete_output', 'unavailable', 'source_capacity', 'partial_unavailable', 'partial_timeout', 'location']);
    }

    public function test_current_profile_planner_rejection_is_honest_saved_interpretation_failure_without_generation(): void
    {
        Queue::fake();
        [$initial] = $this->fixture('completed');
        $conversation = $initial->conversation;
        $user = $initial->createdBy;
        $question = $initial->display_text;
        $initial->delete();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['detail' => [
            'code' => 'retrieval_planning_failed', 'category' => 'invalid_grounded_needs_plan',
            'provider_status' => 200, 'attempt_count' => 2, 'systemic' => false,
            'validation' => ['stage' => 'grounding', 'field' => 'needs.0.question_span', 'code' => 'question_span_grounding'],
            'completion' => ['finish_reason' => 'stop', 'input_tokens' => 120, 'output_tokens' => 90, 'reasoning_tokens' => 50],
        ]], 503));
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, $question, (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame('retrieval_no_answer', $run->status->value);
        $this->assertSame('interpretation_failed', $run->retrievalOutcomeSnapshot->outcome->value);
        $this->assertStringContainsString("couldn't interpret", $run->assistantMessage->display_text);
        $this->assertNull($run->failure_code);
        $this->assertDatabaseCount('generated_answers', 0);
        Http::assertSentCount(1);
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
        if (in_array($mode, ['completed', 'partial_unavailable', 'partial_timeout', 'location'], true)) {
            $payload = $this->payloads[0];
            $this->assertCount(5, $payload['sources']);
            $this->assertSame(in_array($mode, ['completed', 'location'], true) ? ['n1', 'n2'] : ['n1'], array_column($payload['sources'][0]['associations'], 'need_id'));
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
        return [['invalid_citation'], ['duplicate_citation'], ['stale_generation_identity'], ['changed_source'], ['membership_revoked']];
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
            'embedding_space_generation_id' => $space->id, 'sparse_space_generation_id' => $sparseSpace->id,
            'expected_point_count' => $mode === 'fifteen' ? 16 : 8,
            'point_manifest_digest' => hash('sha256', 'controlled-current-policy-fixture'), 'verified_at' => now()]);
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
                if ($this->producedReceipt === 'smoke03_s6' && $doc->id === $document->id) {
                    // Isolated catalogue identities, exact retained source text.
                    $fixture = json_decode(file_get_contents(base_path('../ai/tests/fixtures/current-policy-smoke03-s6.json')), true, flags: JSON_THROW_ON_ERROR);
                    $text = $fixture['request']['sources'][$i - 1]['text'] ?? $text;
                }
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
        if ($mode === 'location') {
            $response['plan']['needs'][1]['question_span']['quote'] = 'Who checks the equipment at Cedar outreach?';
            $response['plan']['needs'][1]['applicability'] = ['kind' => 'named', 'source' => ['turn_id' => 'user-1', 'quote' => 'Cedar outreach']];
        }
        $response['plan']['needs'][] = [...$second, 'need_id' => 'n3', 'question_span' => ['turn_id' => 'user-1', 'quote' => 'Which arrangement do you mean?'], 'requested_information' => 'unclear'];
        $response['plan']['bindings'] = array_map(fn ($id) => ['need_id' => $id, 'subject_status' => 'topic', 'family_ids' => [], 'ownership' => 'authorised_workspace', 'ownership_source' => null], ['n1', 'n2', 'n3']);
        if ($mode === 'current_only') {
            $response['plan']['needs'] = [[...$second, 'need_id' => 'n1']];
            $response['plan']['bindings'] = [$response['plan']['bindings'][0]];
        }
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
            if ($this->producedReceipt !== null) {
                if ($this->producedReceipt === 'service_transport') {
                    throw new ConnectionException('Controlled transport unavailable');
                }
                $rows = json_decode(file_get_contents('/contracts/http/current-policy-answer/v1/controlled-adapter-receipts.json'), true, flags: JSON_THROW_ON_ERROR);
                $receipt = $this->producedReceipt === 'smoke03_s6'
                    ? json_decode(file_get_contents('/contracts/http/current-policy-answer/v1/smoke03-s6-canonical-receipt.json'), true, flags: JSON_THROW_ON_ERROR)
                    : $rows[$this->producedReceipt === 'malformed_usage' ? 'supported' : $this->producedReceipt];
                // Only request correlation is supplied by this controlled transport;
                // usage, result and identity are the real Python adapter output.
                $receipt['request_id'] = $b['request_id'];
                if ($this->producedReceipt === 'malformed_usage') {
                    $receipt['usage']['cost_basis'] = $receipt['usage']['pricing_snapshot'];
                }

                return Http::response($receipt);
            }
            if ($mode === 'changed_source') {
                DB::table('document_chunks')->where('id', $chunks[0]->id)->update(['text' => 'changed']);
            }
            if ($mode === 'membership_revoked') {
                WorkspaceMembership::where('workspace_id', $workspace->id)->delete();
            }
            $base = ['contract_version' => 'current-policy-answer-v1', 'request_id' => $b['request_id'],
                'identity' => ['provider' => config('generation.provider'), 'model' => config('generation.model'),
                    'prompt' => 'current-policy-pool-generation-v1', 'adapter' => 'current-policy-responses-v2',
                    'reasoning_effort' => config('generation.quality_affecting_configuration.reasoning_effort'), 'max_output_tokens' => 4096, 'max_retries' => 0],
                'usage' => ['request_count' => $mode === 'capacity' ? 0 : 1, 'retry_count' => 0]];
            if (in_array($mode, ['invalid_output', 'incomplete_output'], true)) {
                return Http::response([...$base, 'status' => 'failed', 'reason' => $mode]);
            }
            if ($mode === 'capacity') {
                return Http::response([...$base, 'status' => 'capacity', 'request_units' => 120001]);
            }
            if ($mode === 'stale_generation_identity') {
                $base['identity']['adapter'] = 'current-policy-responses-v1';
            }
            if ($mode === 'duplicate_citation') {
                return Http::response([...$base, 'status' => 'completed', 'result' => ['outcome' => 'answered',
                    'answer_parts' => [['text' => 'Wear gloves.', 'evidence_ids' => ['ev-01', 'ev-01']]], 'unsupported_aspects' => []]]);
            }

            return Http::response([...$base, 'status' => 'completed', 'result' => ['outcome' => 'qualified',
                'answer_parts' => [['text' => 'Wear gloves.', 'evidence_ids' => [$mode === 'invalid_citation' ? 'ev-99' : ($mode === 'fifteen' ? 'ev-11' : 'ev-01')]]],
                'unsupported_aspects' => ['The checker role is not established.']]]);
        });

        return [$message, app(GenerationProfileFactory::class)->currentPolicyPin(), $document];
    }
}
