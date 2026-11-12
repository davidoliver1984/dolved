<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Conversation\ReconcileStaleGenerationRuns;
use App\Actions\Conversation\RetryGenerationRun;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Enums\ChatStreamEventType;
use App\Enums\ConversationStatus;
use App\Enums\DocumentStatus;
use App\Enums\GenerationRunFailureCode;
use App\Enums\GenerationRunStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\ExecuteGenerationRun;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\GenerationRun;
use App\Models\IngestionEventClaim;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Models\WorkspaceMembership;
use App\Services\Conversation\ChatDeliveryEventRecorder;
use App\Services\Generation\GenerationProfileFactory;
use App\Support\Conversation\InternalFailureDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_failure_diagnostic_bounds_and_redacts_exception_message(): void
    {
        $diagnostic = InternalFailureDiagnostics::from(new LogicException(
            'token=abc123 password: open-sesame user@example.org '.str_repeat('x', 800)
        ));
        $this->assertSame(LogicException::class, $diagnostic['exception_class']);
        $this->assertStringContainsString('token=[redacted]', $diagnostic['message']);
        $this->assertStringContainsString('password=[redacted]', $diagnostic['message']);
        $this->assertStringContainsString('[redacted-email]', $diagnostic['message']);
        $this->assertStringNotContainsString('abc123', $diagnostic['message']);
        $this->assertStringNotContainsString('open-sesame', $diagnostic['message']);
        $this->assertLessThanOrEqual(600, mb_strlen($diagnostic['message']));
    }

    public function test_unclassified_exception_is_recorded_in_internal_trace_without_changing_failure_code(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        config(['conversation.needs_workspace_ids' => []]);
        Http::fake(fn () => throw new LogicException('Diagnostic sentinel token=secret-value'));
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Synthetic question', (string) Str::uuid());
        $trace = [];
        app(OrchestrateConversationRun::class)->handle($run->id,
            function (string $stage, mixed $value) use (&$trace): void {
                $trace[$stage] = $value;
            });
        $run->refresh();
        $this->assertSame(GenerationRunStatus::Failed, $run->status);
        $this->assertSame(GenerationRunFailureCode::InternalFailure, $run->failure_code);
        $this->assertSame(LogicException::class, $trace['internal_failure']['exception_class']);
        $this->assertSame('Diagnostic sentinel token=[redacted]', $trace['internal_failure']['message']);
        $this->assertSame($trace['internal_failure'], $run->usage['internal_failure']);
    }

    public function test_needs_profile_is_immutable_survives_retry_and_rejects_deployment_drift(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();
        [$user, $workspace] = $this->workspaceFixture();
        config(['conversation.needs_workspace_ids' => [$workspace->public_id]]);
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'A synthetic question', (string) Str::uuid());
        $pin = $run->fresh()->needs_profile;
        $this->assertNotNull($pin);
        $reordered = array_reverse($pin, true);
        app(GenerationProfileFactory::class)->pinnedNeeds($reordered);
        try {
            $run->update(['needs_profile' => null]);
            $this->fail('Profile mutation was accepted.');
        } catch (LogicException) {
            $run->refresh();
        }
        config(['conversation.needs_workspace_ids' => [], 'generation.model' => 'changed-after-submission']);
        app(OrchestrateConversationRun::class)->handle($run->id);
        $this->assertSame(GenerationRunStatus::Failed, $run->fresh()->status);
        Http::assertNothingSent();
        $retry = app(RetryGenerationRun::class)->handle($run->fresh(), (string) Str::uuid());
        $this->assertSame($pin, $retry->fresh()->needs_profile);
        $other = app(CreateConversation::class)->handle($workspace, $user);
        $newRun = app(SubmitConversationMessage::class)->handle($other, $user, 'New legacy question', (string) Str::uuid());
        $this->assertNull($newRun->needs_profile);
    }

    public function test_needs_planner_failure_retains_safe_typed_detail_and_stops_before_retrieval(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        config(['conversation.needs_workspace_ids' => [$workspace->public_id]]);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['detail' => [
            'code' => 'retrieval_planning_failed', 'category' => 'invalid_grounded_needs_plan',
            'provider_status' => 200, 'attempt_count' => 1, 'systemic' => false,
            'raw_body' => 'Must never persist this',
            'completion' => ['finish_reason' => 'stop', 'input_tokens' => 120, 'output_tokens' => 90,
                'reasoning_tokens' => 50, 'raw_body' => 'Must never persist this'],
            'validation' => ['stage' => 'catalogue', 'field' => 'root', 'code' => 'binding_coverage',
                'input' => 'Must never persist this'],
        ]], 503));
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Synthetic question', (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame(GenerationRunStatus::RetrievalNoAnswer, $run->status);
        $this->assertNull($run->failure_code);
        $this->assertSame(['category' => 'invalid_grounded_needs_plan', 'provider_status' => 200,
            'attempt_count' => 1, 'systemic' => false,
            'validation' => ['stage' => 'catalogue', 'field' => 'root', 'code' => 'binding_coverage'],
            'completion' => ['finish_reason' => 'stop', 'input_tokens' => 120, 'output_tokens' => 90,
                'reasoning_tokens' => 50], 'validation_attempts' => []], $run->usage['planner_failure']);
        $this->assertStringNotContainsString('Must never persist', json_encode($run->usage));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('generated_answers', 0);
    }

    public static function plannerReceiptCounts(): array
    {
        return [[1], [2]];
    }

    #[DataProvider('plannerReceiptCounts')]
    public function test_needs_clarification_keeps_text_separate_from_the_bounded_reason_code(int $attempts): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        config(['conversation.needs_workspace_ids' => [$workspace->public_id]]);
        $utterance = 'What overtime deal applied when I did that bank-holiday shift?';
        $question = 'What date or period was that bank-holiday shift?';
        Http::fake(function (Request $request) use ($question, $attempts) {
            $identity = ['provider' => 'openai', 'model' => 'gpt-5-mini',
                'contract_schema_version' => 'evidence-needs-plan-v3',
                'prompt_version' => 'bounded-evidence-needs-v24', 'adapter_version' => 'structured-chat-needs-v8'];
            $canonical = $identity;
            ksort($canonical);

            return Http::response(['contract_version' => 2, 'request_id' => $request->data()['request_id'],
                'plan' => ['schema_version' => 'evidence-needs-plan-v3', 'decision' => 'clarify', 'clarification' => $question, 'needs' => []],
                'classifier_lineage' => [...$identity, 'fingerprint' => hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))],
                'usage' => ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini',
                    'execution' => 'provider_api', 'request_count' => $attempts, 'retry_count' => $attempts - 1,
                    'input_tokens' => 10 * $attempts, 'cached_input_tokens' => 0, 'output_tokens' => 10 * $attempts,
                    'latency_ms' => $attempts, 'cost_basis' => 'unavailable', 'cost_usd' => null, 'pricing_snapshot' => null,
                    'validation_attempts' => array_map(fn ($n) => ['request_count' => 1, 'retry_count' => 0,
                        'input_tokens' => 10, 'output_tokens' => 10, 'latency_ms' => 1,
                        'status' => $n === $attempts ? 'accepted' : 'rejected',
                        'validation' => $n === $attempts ? null : ['stage' => 'catalogue', 'field' => 'root',
                            'code' => 'binding_coverage', 'input' => 'PRIVATE-CONTENT']], range(1, $attempts))]]);
        });
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, $utterance, (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame(GenerationRunStatus::ClarificationRequired, $run->status, json_encode($run->only(['failure_code', 'usage'])));
        $this->assertSame('evidence_needs_clarification', $run->retrievalOutcomeSnapshot->clarification_reason);
        $this->assertSame($question, $run->assistantMessage->display_text);
        $receipts = $run->usage['planner_validation_attempts'];
        $this->assertCount($attempts, $receipts);
        $this->assertSame(10 * $attempts, array_sum(array_column($receipts, 'input_tokens')));
        $this->assertStringNotContainsString('PRIVATE-CONTENT', json_encode($receipts));
        if ($attempts === 2) {
            $this->assertSame('binding_coverage', $receipts[0]['validation']['code']);
            $this->assertSame('accepted', $receipts[1]['status']);
        }
        Http::assertSentCount(1);
    }

    #[DataProvider('referenceFailures')]
    public function test_invalid_model_reference_is_not_misrepresented_as_missing_user_information(array $validation, string $reason): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        config(['conversation.needs_workspace_ids' => [$workspace->public_id]]);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['detail' => [
            'code' => 'retrieval_planning_failed', 'category' => 'invalid_grounded_needs_plan',
            'provider_status' => 200, 'attempt_count' => 1, 'systemic' => false,
            'validation' => $validation,
            'completion' => ['finish_reason' => 'stop', 'input_tokens' => 120, 'output_tokens' => 90, 'reasoning_tokens' => 50],
        ]], 503));
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'How does the supplier register work?', (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();
        $this->assertSame(GenerationRunStatus::RetrievalNoAnswer, $run->status);
        $this->assertNull($run->failure_code);
        $this->assertSame('planner_validation_failed', $run->retrievalOutcomeSnapshot->clarification_reason);
        $this->assertSame('interpretation_failed', $run->retrievalOutcomeSnapshot->outcome->value);
        $this->assertStringContainsString("couldn't interpret", $run->assistantMessage->display_text);
        $this->assertStringNotContainsString('Which date', $run->assistantMessage->display_text);
        $this->assertSame($validation, $run->usage['planner_failure']['validation']);
        $this->assertSame(90, $run->usage['planner_failure']['completion']['output_tokens']);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('generated_answers', 0);
    }

    public static function referenceFailures(): array
    {
        $cases = [[['stage' => 'grounding', 'field' => 'root', 'code' => 'reference_grounding'], 'ungrounded_needs_reference']];
        foreach (['value_error', 'fact_date_precision', 'fact_date_calendar', 'fact_date_interval_shape', 'fact_date_interval_reversed'] as $code) {
            $cases[] = [['stage' => 'schema', 'field' => 'needs.0.fact_reference', 'code' => $code], 'invalid_fact_date_reference'];
        }

        return $cases;
    }

    #[DataProvider('calendarGroundingFailureScenarios')]
    public function test_completed_invalid_plans_have_an_honest_controlled_outcome_not_a_date_question(array $codes, int $providerStatus, bool $clarifies): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        config(['conversation.needs_workspace_ids' => [$workspace->public_id]]);
        $attempts = array_map(static fn (string $code): array => [
            'status' => 'rejected', 'request_count' => 1, 'retry_count' => 0,
            'input_tokens' => 120, 'output_tokens' => 90, 'latency_ms' => 1,
            'validation' => ['stage' => 'grounding', 'field' => 'root', 'code' => $code],
        ], $codes);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['detail' => [
            'code' => 'retrieval_planning_failed', 'category' => 'invalid_grounded_needs_plan',
            'provider_status' => $providerStatus, 'attempt_count' => count($attempts), 'systemic' => false,
            'validation' => end($attempts)['validation'], 'validation_attempts' => $attempts,
            'completion' => ['finish_reason' => 'stop', 'input_tokens' => 120,
                'output_tokens' => 90, 'reasoning_tokens' => 50],
        ]], 503));
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user,
            'How much holiday pay will I receive if I leave this month?', (string) Str::uuid());
        app(OrchestrateConversationRun::class)->handle($run->id);
        $run->refresh();

        if ($clarifies) {
            $this->assertSame(GenerationRunStatus::RetrievalNoAnswer, $run->status);
            $this->assertSame('planner_validation_failed', $run->retrievalOutcomeSnapshot->clarification_reason);
            $this->assertStringContainsString("couldn't interpret", $run->assistantMessage->display_text);
            $this->assertStringNotContainsString('Which date', $run->assistantMessage->display_text);
            $this->assertNull($run->failure_code);
        } else {
            $this->assertSame(GenerationRunStatus::Failed, $run->status);
            $this->assertSame('retrieval_failed', $run->failure_code->value);
            $this->assertNull($run->assistantMessage);
        }
        $this->assertCount(count($attempts), $run->usage['planner_failure']['validation_attempts']);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('generated_answers', 0);
    }

    public static function calendarGroundingFailureScenarios(): array
    {
        return [
            'exhausted calendar grounding' => [['calendar_grounding', 'calendar_grounding'], 200, true],
            'single legacy rejection' => [['calendar_grounding'], 200, true],
            'other exhausted validation' => [['binding_coverage', 'binding_coverage'], 200, true],
            'mixed exhausted validation' => [['binding_coverage', 'calendar_grounding'], 200, true],
            'provider failure cannot become clarification' => [['calendar_grounding', 'calendar_grounding'], 503, false],
        ];
    }

    public function test_submission_is_workspace_scoped_idempotent_and_allows_only_one_active_run(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $conversationId = $this->actingAs($user)
            ->postJson("/api/workspaces/{$workspace->public_id}/conversations")
            ->assertCreated()
            ->json('data.id');
        $this->getJson("/api/workspaces/{$workspace->public_id}/conversations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversationId);
        $key = (string) Str::uuid();
        $path = "/api/workspaces/{$workspace->public_id}/conversations/{$conversationId}/messages";

        $first = $this->postJson($path, ['message' => 'What is the current procedure?', 'idempotency_key' => $key])
            ->assertAccepted();
        $this->postJson($path, ['message' => 'What is the current procedure?', 'idempotency_key' => $key])
            ->assertAccepted()
            ->assertJsonPath('data.run_id', $first->json('data.run_id'));
        $this->postJson($path, ['message' => 'Different content', 'idempotency_key' => $key])->assertConflict();
        $this->postJson($path, ['message' => 'Another question', 'idempotency_key' => (string) Str::uuid()])->assertConflict();

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('generation_runs', 1);
        Queue::assertPushed(ExecuteGenerationRun::class, 1);

        $foreign = Workspace::factory()->withOwner($user)->create();
        $this->postJson(
            "/api/workspaces/{$foreign->public_id}/conversations/{$conversationId}/messages",
            ['message' => 'Cross workspace', 'idempotency_key' => (string) Str::uuid()],
        )->assertNotFound();
        $this->getJson(
            "/api/workspaces/{$foreign->public_id}/conversations/{$conversationId}",
        )->assertNotFound();
        $this->getJson(
            "/api/workspaces/{$workspace->public_id}/conversations/".(string) Str::uuid(),
        )->assertNotFound();

        Conversation::query()->where('public_id', $conversationId)->update([
            'status' => ConversationStatus::Deleted->value,
        ]);
        $this->getJson(
            "/api/workspaces/{$workspace->public_id}/conversations/{$conversationId}",
        )->assertNotFound();
    }

    public function test_failed_run_reports_when_provisional_content_was_retracted(): void
    {
        Queue::fake();
        [$run, $conversation] = $this->runFixture();
        app(ChatDeliveryEventRecorder::class)->record(
            $run,
            ChatStreamEventType::AnswerPartAcceptedForDisplay,
            ['text' => 'Provisional text', 'citations' => []],
            true,
        );
        $run->update([
            'status' => GenerationRunStatus::Failed,
            'failure_code' => GenerationRunFailureCode::StreamProtocolFailure,
            'completed_at' => now(),
        ]);

        $this->actingAs($run->userMessage->createdBy)
            ->getJson("/api/workspaces/{$conversation->workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.runs.0.status', 'failed')
            ->assertJsonPath('data.runs.0.failure_code', 'stream_protocol_failure')
            ->assertJsonPath('data.runs.0.provisional_content_retracted', true);
    }

    public function test_queued_cancellation_is_terminal_and_retry_is_idempotent(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $conversationId = $this->actingAs($user)->postJson("/api/workspaces/{$workspace->public_id}/conversations")->json('data.id');
        $runId = $this->postJson(
            "/api/workspaces/{$workspace->public_id}/conversations/{$conversationId}/messages",
            ['message' => 'Question', 'idempotency_key' => (string) Str::uuid()],
        )->json('data.run_id');
        $base = "/api/workspaces/{$workspace->public_id}/conversations/{$conversationId}/runs/{$runId}";
        $this->postJson("{$base}/cancel")->assertAccepted()->assertJsonPath('data.status', 'cancelled');

        $retryKey = (string) Str::uuid();
        $retry = $this->postJson("{$base}/retry", ['idempotency_key' => $retryKey])->assertAccepted();
        $this->postJson("{$base}/retry", ['idempotency_key' => $retryKey])
            ->assertAccepted()
            ->assertJsonPath('data.run_id', $retry->json('data.run_id'));
        $this->assertDatabaseCount('generation_runs', 2);
    }

    public function test_contextualisation_clarification_completes_without_retrieval_or_generation(): void
    {
        Queue::fake();
        [$run] = $this->runFixture();
        Http::fake(function ($request) {
            $body = $request->data();

            return Http::response([
                'contract_version' => 1,
                'request_id' => $body['request_id'],
                'result' => [
                    'status' => 'clarification_required',
                    'resolved_query' => null,
                    'used_prior_context' => false,
                    'interpretation_metadata' => ['used_turn_ordinals' => []],
                    'clarification_question' => 'Which procedure do you mean?',
                    'contextualiser_version' => 'conversation-context-v1',
                    'usage' => ['execution' => 'deterministic', 'request_count' => 0],
                ],
            ]);
        });

        app(OrchestrateConversationRun::class)->handle($run->id);

        $run->refresh();
        $this->assertSame(GenerationRunStatus::ClarificationRequired, $run->status);
        $this->assertDatabaseHas('messages', ['role' => MessageRole::Assistant->value, 'kind' => MessageKind::Clarification->value]);
        $this->assertDatabaseCount('contextualisation_result_snapshots', 1);
        $this->assertDatabaseCount('retrieval_outcome_snapshots', 0);
        $this->assertDatabaseCount('generated_answers', 0);
        Http::assertSentCount(1);
    }

    public function test_no_eligible_evidence_maps_to_durable_controlled_no_answer(): void
    {
        Queue::fake();
        [$run] = $this->runFixture();
        Http::fake(function ($request) {
            $body = $request->data();
            if (str_ends_with($request->url(), '/conversation/contextualize')) {
                return Http::response([
                    'contract_version' => 1,
                    'request_id' => $body['request_id'],
                    'result' => [
                        'status' => 'resolved', 'resolved_query' => $body['current_message'],
                        'used_prior_context' => false, 'interpretation_metadata' => ['used_turn_ordinals' => []],
                        'clarification_question' => null, 'contextualiser_version' => 'conversation-context-v1',
                        'usage' => ['execution' => 'deterministic', 'request_count' => 0],
                    ],
                ]);
            }

            return Http::response($this->planResponse($body));
        });

        app(OrchestrateConversationRun::class)->handle($run->id);

        $run->refresh();
        $this->assertSame(GenerationRunStatus::RetrievalNoAnswer, $run->status);
        $this->assertSame('no_eligible_evidence', $run->retrieval_outcome?->value);
        $this->assertDatabaseHas('messages', ['role' => 'assistant', 'kind' => 'no_answer']);
        $this->assertDatabaseCount('retrieval_outcome_snapshots', 1);
        $this->assertDatabaseCount('controlled_assistant_responses', 1);
        $this->assertDatabaseCount('generated_answers', 0);
        Http::assertSentCount(2);
    }

    public static function executionPaths(): array
    {
        return [['legacy'], ['needs'], ['needs', 'contract_validation_failure', 200], ['needs', 'rate_limit', 429], ['needs', 'PRIVATE-CONTENT', 999]];
    }

    #[DataProvider('executionPaths')]
    public function test_evidence_found_runs_the_complete_generation_and_persists_one_authoritative_answer(string $path, ?string $failureCategory = null, ?int $failureStatus = null): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        config(['conversation.needs_workspace_ids' => $path === 'needs' ? [$workspace->public_id] : []]);
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
        $document = Document::factory()->indexed()->approved()->create([
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $user->id,
            'source_filename' => 'policies/Medication procedure.pdf',
            'media_type' => 'application/pdf',
            'size_bytes' => 4096,
            'effective_from' => '2026-01-01 00:00:00',
            'approved_at' => '2026-01-02 00:00:00',
        ]);
        $claim = IngestionEventClaim::factory()->create([
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
        ]);
        $chunk = DocumentChunk::factory()->create([
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
            'ingestion_event_claim_id' => $claim->id,
            'text' => 'The current approved procedure requires a documented safety check.',
            'content_digest' => hash('sha256', 'The current approved procedure requires a documented safety check.'),
        ]);
        WorkspaceCorpusGenerationChunk::factory()->create([
            'workspace_id' => $workspace->id,
            'workspace_corpus_generation_id' => $generation->id,
            'document_chunk_id' => $chunk->id,
        ]);
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle(
            $conversation, $user, 'What does the current approved procedure require?', (string) Str::uuid(),
        );
        // Rollback controls new submissions, never queued work.
        config(['conversation.needs_workspace_ids' => []]);
        $this->assertSame($path === 'needs', $run->needs_profile !== null);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($generation, $document, $chunk, $path, $failureCategory, $failureStatus) {
            $body = $request->data();
            if (str_ends_with($request->url(), '/plan-needs')) {
                $this->assertSame([['turn_id' => 'user-1', 'text' => 'What does the current approved procedure require?']], $body['user_turns']);
                $lineage = ['provider' => 'openai', 'model' => 'gpt-5-mini',
                    'contract_schema_version' => 'evidence-needs-plan-v3',
                    'prompt_version' => 'bounded-evidence-needs-v24', 'adapter_version' => 'structured-chat-needs-v8'];
                ksort($lineage);
                $lineage['fingerprint'] = hash('sha256', json_encode($lineage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return Http::response(['contract_version' => 2, 'request_id' => $body['request_id'],
                    'plan' => ['schema_version' => 'evidence-needs-plan-v3', 'decision' => 'plan', 'clarification' => null,
                        'bindings' => [['need_id' => 'n1', 'subject_status' => 'topic', 'family_ids' => [],
                            'ownership' => 'authorised_workspace', 'ownership_source' => null]],
                        'needs' => [['need_id' => 'n1', 'aspect' => 'current procedure requirements', 'central' => true,
                            'question_span' => ['turn_id' => 'user-1', 'quote' => $body['user_turns'][0]['text']],
                            'operation' => 'retrieve', 'authority' => ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null],
                            'fact_reference' => null, 'applicability' => ['kind' => 'generic', 'source' => null], 'supports_need_id' => null]]],
                    'classifier_lineage' => $lineage,
                    'usage' => ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini', 'execution' => 'provider_api',
                        'request_count' => 1, 'retry_count' => 0, 'input_tokens' => 12, 'cached_input_tokens' => null,
                        'output_tokens' => 20, 'latency_ms' => 1, 'cost_basis' => 'unavailable', 'cost_usd' => null, 'pricing_snapshot' => null]]);
            }
            if (str_ends_with($request->url(), '/conversation/contextualize')) {
                return Http::response([
                    'contract_version' => 1, 'request_id' => $body['request_id'],
                    'result' => ['status' => 'resolved', 'resolved_query' => $body['current_message'], 'used_prior_context' => false, 'interpretation_metadata' => ['used_turn_ordinals' => []], 'clarification_question' => null, 'contextualiser_version' => 'conversation-context-v1', 'usage' => ['execution' => 'deterministic', 'request_count' => 0]],
                ]);
            }
            if (str_ends_with($request->url(), '/plan')) {
                return Http::response($this->planResponse($body));
            }
            if (str_ends_with($request->url(), '/search')) {
                return Http::response([
                    'contract_version' => 1, 'request_id' => $body['request_id'],
                    'lineage' => ['embedding_profile_fingerprint' => $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint],
                    'candidates' => [[
                        'chunk_id' => $chunk->public_id, 'document_id' => $document->public_id,
                        'workspace_corpus_generation_id' => $generation->public_id,
                        'embedding_space_generation_id' => $generation->embeddingSpaceGeneration->public_id,
                        'score' => 0.82, 'rank' => 1, 'retrieval_method' => 'dense', 'side' => 'primary',
                    ]],
                ]);
            }

            if ($failureCategory !== null) {
                return Http::response(['contract_version' => 2, 'request_id' => $body['request_id'], 'status' => 'provider_error',
                    'error' => ['category' => $failureCategory, 'provider' => 'PRIVATE-PROVIDER', 'model' => 'PRIVATE-MODEL',
                        'http_status' => $failureStatus, 'attempt_count' => 1, 'latency_ms' => 12.5,
                        'validation' => $failureCategory === 'contract_validation_failure'
                            ? ['field' => 'answer_parts.text', 'code' => 'detached_polarity']
                            : ['field' => 'PRIVATE-CONTENT', 'code' => 'PRIVATE-CONTENT']]]);
            }
            $result = [
                'contract_version' => 1, 'request_id' => $body['request_id'], 'status' => 'completed',
                'result' => [
                    'contract_version' => 1, 'outcome' => 'answered',
                    'answer_parts' => [['text' => 'It requires a documented safety check.', 'evidence_ids' => ['ev-01']]],
                    'unsupported_aspects' => [], 'insufficiency_reason' => null,
                    'usage' => ['latency_ms' => 2, 'input_tokens' => 20, 'output_tokens' => 8, 'cost_usd' => null],
                ],
            ];
            if ($path === 'needs') {
                $this->assertStringEndsWith('/generation/answer-needs', $request->url());
                $this->assertSame(['ev-01'], $body['evidence_needs'][0]['evidence_ids']);
                $result['contract_version'] = 2;
                $result['result']['contract_version'] = 3;
                $result['result']['aspect_links'] = [['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []]];
                $result['result']['entailment_checks'] = [['part_index' => 0, 'verdict' => 'supported', 'missing_condition' => null]];

                return Http::response($result);
            }
            $candidate = [
                'contract_version' => 1, 'request_id' => $body['request_id'], 'sequence' => 1,
                'event_type' => 'answer_part_candidate',
                'candidate' => ['text' => 'It requires a documented safety check.', 'evidence_ids' => ['ev-01']],
                'result' => null, 'error' => null, 'failure' => null,
            ];
            $terminal = [
                'contract_version' => 1, 'request_id' => $body['request_id'], 'sequence' => 2,
                'event_type' => 'generation_completed', 'candidate' => null,
                'result' => $result['result'], 'error' => null, 'failure' => null,
            ];

            return Http::response(json_encode($candidate, JSON_THROW_ON_ERROR)."\n".json_encode($terminal, JSON_THROW_ON_ERROR)."\n", 200, ['Content-Type' => 'application/x-ndjson']);
        });

        app(OrchestrateConversationRun::class)->handle($run->id);

        $run->refresh();
        if ($failureCategory !== null) {
            $this->assertSame(GenerationRunStatus::Failed, $run->status);
            $this->assertSame($failureCategory === 'contract_validation_failure'
                ? GenerationRunFailureCode::GenerationContractInvalid
                : GenerationRunFailureCode::ProviderUnavailable, $run->failure_code);
            $this->assertSame($failureCategory === 'PRIVATE-CONTENT' ? 'unknown_generation_failure' : $failureCategory, $run->usage['generation']['category']);
            $this->assertSame($failureStatus === 999 ? null : $failureStatus, $run->usage['generation']['http_status']);
            $this->assertSame($failureCategory === 'contract_validation_failure' ? 'answer_parts.text' : null, $run->usage['generation']['field']);
            $this->assertSame($failureCategory === 'contract_validation_failure' ? 'detached_polarity' : null, $run->usage['generation']['code']);
            $this->assertSame(1, $run->usage['generation']['attempt_count']);
            $this->assertStringNotContainsString('PRIVATE', json_encode($run->usage));
            $this->assertNull($run->generatedAnswer);
            Http::assertSentCount(3);

            return;
        }
        $this->assertSame(
            GenerationRunStatus::Completed,
            $run->status,
            json_encode($run->retrievalOutcomeSnapshot?->toArray(), JSON_THROW_ON_ERROR),
        );
        $this->assertDatabaseCount('generated_answers', 1);
        $this->assertDatabaseCount('answer_parts', 1);
        $this->assertDatabaseCount('evidence_snapshots', 1);
        $this->assertDatabaseHas('messages', ['id' => $run->assistant_message_id, 'kind' => 'grounded_answer']);
        $this->assertSame($run->id, $run->generatedAnswer?->generation_run_id);
        $this->assertSame($path === 'needs' ? 'complete_result_only' : 'streaming_parts', $run->delivery_mode);
        $this->assertSame($path === 'needs' ? 'generation.answer-needs' : 'generation.stream', $run->generation_endpoint);
        $events = $run->deliveryEvents()->get();
        if ($path === 'needs') {
            $this->assertSame(['n1' => 'supported'], $run->generatedAnswer->aspect_results);
            $this->assertSame('evidence-needs-plan-v3', $run->retrievalOutcomeSnapshot->lineage['evidence_needs_plan']['schema_version']);
            $this->assertFalse($events->contains(fn ($event) => $event->provisional));
            Http::assertSentCount(3);
        } else {
            $this->assertSame([
                'run_progress', 'run_progress', 'run_progress', 'run_progress',
                'answer_part_accepted_for_display', 'run_progress', 'answer_completed',
            ], $events->pluck('event_type')->map->value->all());
            $this->assertTrue($events[4]->provisional);
            $this->assertFalse($events[6]->provisional);
            $provisional = json_encode($events[4]->safe_payload, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('ev-01', $provisional);
            $this->assertStringNotContainsString('document_chunk_id', $provisional);
            $this->assertSame($run->assistantMessage?->public_id, $events[6]->safe_payload['message_id']);
        }
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.runs.0.answer.outcome', 'answered')
            ->assertJsonPath('data.runs.0.answer.parts.0.text', 'It requires a documented safety check.')
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.document_id', $document->public_id)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.display_name', 'Medication procedure.pdf')
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.type_label', 'PDF document')
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.size_bytes', 4096)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_state', 'available')
            ->assertJsonPath(
                'data.runs.0.answer.parts.0.citations.0.source_route',
                "/app/workspaces/{$workspace->public_id}/documents/{$document->public_id}",
            )
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.cited_text', $chunk->text)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_provenance', $chunk->provenance);

        DB::table('documents')->where('id', $document->id)->update(['media_type' => 'application/octet-stream']);
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.document_id', null)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_state', 'unavailable')
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_route', null);

        DB::table('documents')->where('id', $document->id)->update(['media_type' => 'application/pdf']);
        $document->forceFill(['status' => DocumentStatus::Deleted])->save();
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.document_id', null)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_state', 'removed')
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_route', null)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.cited_text', $chunk->text)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_provenance', $chunk->provenance);
        Http::assertSentCount($path === 'needs' ? 3 : 4);
    }

    public function test_sse_replays_after_last_event_id_and_closes_on_terminal_event(): void
    {
        Queue::fake();
        [$run, $conversation] = $this->runFixture();
        $recorder = app(ChatDeliveryEventRecorder::class);
        $recorder->record($run, ChatStreamEventType::RunProgress, ['stage' => 'retrieving', 'display_key' => 'conversation.progress.retrieving']);
        $recorder->record($run, ChatStreamEventType::RunFailed, ['failure_code' => 'internal_failure', 'retryable' => true]);

        $response = $this->actingAs($run->userMessage->createdBy)->withHeader('Last-Event-ID', '1')->get(
            "/api/workspaces/{$conversation->workspace->public_id}/conversations/{$conversation->public_id}/runs/{$run->public_id}/events",
        );

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8')
            ->assertHeader('X-Accel-Buffering', 'no');
        $this->assertStringContainsString('no-cache, no-transform', (string) $response->headers->get('Cache-Control'));
        $content = $response->streamedContent();
        $this->assertStringNotContainsString('id: 1', $content);
        $this->assertStringContainsString("id: 2\nevent: run_failed", $content);
        $this->assertStringContainsString('"failure_code":"internal_failure"', $content);

        $foreign = Workspace::factory()->withOwner()->create();
        $this->get("/api/workspaces/{$foreign->public_id}/conversations/{$conversation->public_id}/runs/{$run->public_id}/events")
            ->assertNotFound();
    }

    public function test_e2e_event_limit_closes_after_one_durable_event_for_native_reconnect(): void
    {
        Queue::fake();
        config()->set('conversation.sse_event_limit_per_connection', 1);
        [$run, $conversation] = $this->runFixture();
        $recorder = app(ChatDeliveryEventRecorder::class);
        $recorder->record($run, ChatStreamEventType::RunProgress, ['stage' => 'retrieving']);
        $recorder->record($run, ChatStreamEventType::RunFailed, ['failure_code' => 'internal_failure']);
        $path = "/api/workspaces/{$conversation->workspace->public_id}/conversations/{$conversation->public_id}/runs/{$run->public_id}/events";

        $first = $this->actingAs($run->userMessage->createdBy)->get($path)->streamedContent();
        $this->assertStringContainsString("id: 1\nevent: run_progress", $first);
        $this->assertStringNotContainsString('id: 2', $first);

        $second = $this->actingAs($run->userMessage->createdBy)
            ->withHeader('Last-Event-ID', '1')
            ->get($path)
            ->streamedContent();
        $this->assertStringNotContainsString('id: 1', $second);
        $this->assertStringContainsString("id: 2\nevent: run_failed", $second);
    }

    public function test_open_sse_stream_terminates_before_delivering_more_events_after_membership_revocation(): void
    {
        Queue::fake();
        [$run, $conversation] = $this->runFixture();
        app(ChatDeliveryEventRecorder::class)->record(
            $run,
            ChatStreamEventType::RunProgress,
            ['stage' => 'retrieving', 'display_key' => 'conversation.progress.retrieving'],
        );
        $user = $run->userMessage->createdBy;
        $response = $this->actingAs($user)->get(
            "/api/workspaces/{$conversation->workspace->public_id}/conversations/{$conversation->public_id}/runs/{$run->public_id}/events",
        );

        WorkspaceMembership::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('user_id', $user->id)
            ->delete();

        $content = $response->streamedContent();
        $this->assertStringContainsString('event: authorization_revoked', $content);
        $this->assertStringContainsString('"code":"workspace_membership_revoked"', $content);
        $this->assertStringNotContainsString('event: run_progress', $content);
    }

    public function test_message_role_invariants_and_immutability_fail_closed(): void
    {
        [$run] = $this->runFixture();
        $message = $run->userMessage;
        try {
            $message->update(['display_text' => 'Changed']);
            $this->fail('Expected immutable message update to fail.');
        } catch (LogicException) {
            $this->assertSame('Question', $message->fresh()->display_text);
        }

        $this->expectException(LogicException::class);
        Message::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $run->workspace_id,
            'conversation_id' => $run->conversation_id, 'ordinal' => 2,
            'role' => MessageRole::Assistant, 'kind' => null, 'display_text' => 'Invalid',
            'created_by_user_id' => null, 'in_reply_to_message_id' => $message->id,
        ]);
    }

    public function test_stale_active_runs_fail_closed_and_stale_cancellation_is_acknowledged(): void
    {
        Queue::fake();
        [$run] = $this->runFixture();
        DB::table('generation_runs')->where('id', $run->id)->update(['updated_at' => now()->subHour()]);

        $this->assertSame(1, app(ReconcileStaleGenerationRuns::class)->handle());
        $this->assertSame(GenerationRunStatus::Failed, $run->fresh()->status);
        $this->assertSame('run_timeout', $run->fresh()->failure_code?->value);

        DB::table('chat_delivery_events')->where('generation_run_id', $run->id)->delete();
        DB::table('generation_runs')->where('id', $run->id)->update([
            'status' => GenerationRunStatus::CancellationRequested,
            'completed_at' => null,
            'failure_code' => null,
            'updated_at' => now()->subHour(),
        ]);
        $this->assertSame(1, app(ReconcileStaleGenerationRuns::class)->handle());
        $this->assertSame(GenerationRunStatus::Cancelled, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->cancellation_acknowledged_at);
    }

    /** @return array{User, Workspace} */
    private function workspaceFixture(): array
    {
        $user = User::factory()->create();

        return [$user, Workspace::factory()->withOwner($user)->create()];
    }

    /** @return array{GenerationRun, Conversation} */
    private function runFixture(): array
    {
        [$user, $workspace] = $this->workspaceFixture();
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle(
            $conversation, $user, 'Question', (string) Str::uuid(),
        );

        return [$run->fresh(['userMessage']), $conversation];
    }

    /** @param array<string, mixed> $body */
    private function planResponse(array $body): array
    {
        $lineage = [
            'provider' => 'deterministic', 'model' => 'fixed-retrieval-planner',
            'contract_schema_version' => 'plan-response-v2', 'prompt_version' => 'fixed-v1',
            'adapter_version' => 'fixed-v1',
        ];
        $fingerprintInput = $lineage;
        ksort($fingerprintInput);

        return [
            'contract_version' => 2, 'request_id' => $body['request_id'],
            'plan' => ['retrieval_queries' => [$body['question']], 'temporal_mode' => 'current', 'explicit_date' => null, 'temporal_reference' => null, 'location_references' => [], 'clarification_reason' => null],
            'classifier_lineage' => $lineage + ['fingerprint' => hash('sha256', json_encode($fingerprintInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))],
            'usage' => ['stage' => 'planner', 'provider' => 'deterministic', 'model' => 'fixed-retrieval-planner', 'execution' => 'local', 'request_count' => 1, 'retry_count' => 0, 'input_tokens' => null, 'cached_input_tokens' => null, 'output_tokens' => null, 'latency_ms' => 0, 'cost_basis' => 'zero_cost_local', 'cost_usd' => 0, 'pricing_snapshot' => null],
        ];
    }
}
