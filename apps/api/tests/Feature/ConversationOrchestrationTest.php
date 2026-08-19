<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Conversation\ReconcileStaleGenerationRuns;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Enums\ChatStreamEventType;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ConversationOrchestrationTest extends TestCase
{
    use RefreshDatabase;

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

        $foreign = Workspace::factory()->withOwner()->create();
        $this->postJson(
            "/api/workspaces/{$foreign->public_id}/conversations/{$conversationId}/messages",
            ['message' => 'Cross workspace', 'idempotency_key' => (string) Str::uuid()],
        )->assertNotFound();
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

    public function test_evidence_found_runs_the_complete_generation_and_persists_one_authoritative_answer(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
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
        Http::fake(function ($request) use ($generation, $document, $chunk) {
            $body = $request->data();
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

            $result = [
                'contract_version' => 1, 'request_id' => $body['request_id'], 'status' => 'completed',
                'result' => [
                    'contract_version' => 1, 'outcome' => 'answered',
                    'answer_parts' => [['text' => 'It requires a documented safety check.', 'evidence_ids' => ['ev-01']]],
                    'unsupported_aspects' => [], 'insufficiency_reason' => null,
                    'usage' => ['latency_ms' => 2, 'input_tokens' => 20, 'output_tokens' => 8, 'cost_usd' => null],
                ],
            ];
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
        $this->assertSame('streaming_parts', $run->delivery_mode);
        $this->assertSame('generation.stream', $run->generation_endpoint);
        $events = $run->deliveryEvents()->get();
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
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.runs.0.answer.outcome', 'answered')
            ->assertJsonPath('data.runs.0.answer.parts.0.text', 'It requires a documented safety check.')
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.document_id', $document->public_id)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.cited_text', $chunk->text)
            ->assertJsonPath('data.runs.0.answer.parts.0.citations.0.source_provenance', $chunk->provenance);
        Http::assertSentCount(4);
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
