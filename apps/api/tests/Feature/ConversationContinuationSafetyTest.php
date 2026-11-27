<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\CancelGenerationRun;
use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\OrchestrateDifyConversationRun;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Models\DifyConversationSession;
use App\Models\DifyWorkspaceBinding;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConversationContinuationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_dify_conversation_continues_with_the_exact_latest_question(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $this->enableDify($workspace);
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        DifyConversationSession::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'remote_conversation_id' => $remoteConversationId = (string) Str::uuid(),
        ]);
        $run = app(SubmitConversationMessage::class)->handle(
            $conversation,
            $user,
            'What changed today?',
            (string) Str::uuid(),
        );
        Http::preventStrayRequests();
        Http::fake(['http://dify.test/v1/chat-messages' => Http::response($this->difyResponse($remoteConversationId))]);

        app(OrchestrateDifyConversationRun::class)->handle($run->id);

        Http::assertSent(fn (Request $request): bool => $request['query'] === 'What changed today?'
            && $request['conversation_id'] === $remoteConversationId);
        $this->assertSame('completed', $run->fresh()->status->value);
        $this->assertNull($conversation->fresh()->continuation_blocked_at);
    }

    public function test_post_dispatch_cancellation_discards_the_late_answer_and_blocks_continuation(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $this->enableDify($workspace);
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        DifyConversationSession::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'remote_conversation_id' => $remoteConversationId = (string) Str::uuid(),
        ]);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Stop this answer', (string) Str::uuid());
        Http::preventStrayRequests();
        Http::fake(function () use ($remoteConversationId, $run) {
            app(CancelGenerationRun::class)->handle($run);

            return Http::response($this->difyResponse($remoteConversationId, 'Discarded late answer.'));
        });

        app(OrchestrateDifyConversationRun::class)->handle($run->id);

        $this->assertSame('cancelled', $run->fresh()->status->value);
        $this->assertSame('completed_after_cancel', $run->fresh()->provider_dispatch_state);
        $this->assertNull($run->fresh()->assistant_message_id);
        $this->assertNotNull($conversation->fresh()->continuation_blocked_at);
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.continuation.reason', 'remote_history_inconsistent');
        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}/messages",
            ['message' => 'Can this bypass the block?', 'idempotency_key' => (string) Str::uuid()],
        )->assertConflict()->assertJsonPath(
            'message',
            'This conversation could not be safely continued after the interrupted response. Start a new conversation to continue.',
        );
    }

    public function test_queued_cancellation_does_not_block_a_clean_conversation(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Cancel before dispatch', (string) Str::uuid());

        app(CancelGenerationRun::class)->handle($run);

        $this->assertSame('cancelled', $run->fresh()->status->value);
        $this->assertNull($conversation->fresh()->continuation_blocked_at);
        $next = app(SubmitConversationMessage::class)->handle($conversation, $user, 'A clean next question', (string) Str::uuid());
        $this->assertSame('queued', $next->status->value);
    }

    public function test_cancellation_after_preparation_but_before_dispatch_does_not_block_an_established_conversation(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        DifyConversationSession::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'remote_conversation_id' => (string) Str::uuid(),
        ]);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Cancel before the request', (string) Str::uuid());
        $run->update(['status' => 'retrieving', 'provider_dispatch_state' => 'ready_to_dispatch']);

        app(CancelGenerationRun::class)->handle($run);

        $this->assertSame('cancellation_requested', $run->fresh()->status->value);
        $this->assertNull($conversation->fresh()->continuation_blocked_at);
    }

    public function test_uncertain_established_remote_history_blocks_later_submissions_but_preserves_unknown_cost(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $this->enableDify($workspace);
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        DifyConversationSession::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'remote_conversation_id' => (string) Str::uuid(),
        ]);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Uncertain request', (string) Str::uuid());
        Http::preventStrayRequests();
        Http::fake(['http://dify.test/v1/chat-messages' => Http::response([], 500)]);

        app(OrchestrateDifyConversationRun::class)->handle($run->id);

        $this->assertSame('failed', $run->fresh()->status->value);
        $this->assertSame('uncertain', $run->fresh()->provider_dispatch_state);
        $this->assertNull($run->fresh()->usage['dify']['cost_usd'] ?? null);
        $this->assertNotNull($conversation->fresh()->continuation_blocked_at);
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.continuation.reason', 'remote_history_inconsistent');
    }

    public function test_legacy_uncertain_local_evidence_is_blocked_without_a_remote_scan(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        DifyConversationSession::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'remote_conversation_id' => (string) Str::uuid(),
        ]);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Older uncertain request', (string) Str::uuid());
        $run->update(['status' => 'failed', 'provider_dispatch_state' => 'uncertain', 'completed_at' => now()]);

        $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}/messages",
            ['message' => 'Later request', 'idempotency_key' => (string) Str::uuid()],
        )->assertConflict();

        $this->assertNull($conversation->fresh()->continuation_blocked_at);
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.continuation.reason', 'remote_history_inconsistent');
    }

    public function test_retired_location_remains_visible_but_blocks_dispatch_and_future_messages(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $this->enableDify($workspace);
        $site = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Oakfield Lodge']);
        $conversation = app(CreateConversation::class)->handle($workspace, $user, $site);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Question saved before retirement', (string) Str::uuid());
        $site->forceFill(['retired_at' => now()])->save();
        $this->assertNotNull($site->fresh()->retired_at);
        Http::preventStrayRequests();

        app(OrchestrateDifyConversationRun::class)->handle($run->id);

        Http::assertNothingSent();
        $this->assertSame('conversation_context_unavailable', $run->fresh()->failure_code->value);
        $this->assertSame('not_dispatched', $run->fresh()->provider_dispatch_state);
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.site_context.name', 'Oakfield Lodge')
            ->assertJsonPath('data.site_context.status', 'retired')
            ->assertJsonPath('data.continuation.reason', 'location_unavailable')
            ->assertJsonPath('data.messages.0.text', 'Question saved before retirement');
        $this->postJson(
            "/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}/messages",
            ['message' => 'Must not dispatch', 'idempotency_key' => (string) Str::uuid()],
        )->assertConflict();
    }

    public function test_new_conversation_has_no_inherited_remote_identity_and_global_remains_available(): void
    {
        [$user, $workspace] = $this->workspaceFixture();

        $conversation = app(CreateConversation::class)->handle($workspace, $user);

        $this->assertNull($conversation->difySession);
        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.site_context.scope_key', 'global')
            ->assertJsonPath('data.continuation.allowed', true);
    }

    /** @return array{User, Workspace} */
    private function workspaceFixture(): array
    {
        $user = User::factory()->create();

        return [$user, Workspace::factory()->withOwner($user)->create()];
    }

    private function enableDify(Workspace $workspace): void
    {
        DifyWorkspaceBinding::query()->create([
            'workspace_id' => $workspace->id,
            'app_id' => (string) Str::uuid(),
            'dataset_id' => (string) Str::uuid(),
            'scope_metadata_id' => (string) Str::uuid(),
            'enabled' => true,
        ]);
        config(['dify.base_url' => 'http://dify.test', 'dify.app_api_key' => 'server-secret']);
    }

    /** @return array<string, mixed> */
    private function difyResponse(string $conversationId, string $answer = 'Safe answer.'): array
    {
        return [
            'answer' => $answer,
            'conversation_id' => $conversationId,
            'message_id' => (string) Str::uuid(),
            'task_id' => (string) Str::uuid(),
            'metadata' => ['usage' => [], 'retriever_resources' => []],
        ];
    }
}
