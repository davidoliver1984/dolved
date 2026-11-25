<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Enums\GenerationRunStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AnswerFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_feedback_is_tenant_scoped_editable_and_audited(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $conversation = app(CreateConversation::class)->handle($workspace, $owner);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $owner, 'What is the annual leave entitlement?', (string) Str::uuid());
        $assistant = Message::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'ordinal' => 2,
            'role' => MessageRole::Assistant,
            'kind' => MessageKind::GroundedAnswer,
            'display_text' => 'The policy provides 28 days.',
            'created_by_user_id' => null,
            'in_reply_to_message_id' => $run->user_message_id,
            'submission_idempotency_key' => null,
        ]);
        $run->update([
            'assistant_message_id' => $assistant->id,
            'status' => GenerationRunStatus::Completed,
            'completed_at' => now(),
        ]);
        $endpoint = "/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}/runs/{$run->public_id}/feedback";

        $this->actingAs($owner)->putJson($endpoint, [
            'rating' => 'not_helpful',
            'reason_code' => 'incomplete',
            'comment' => 'It did not explain part-time entitlement.',
        ])->assertOk()
            ->assertJsonPath('data.rating', 'not_helpful')
            ->assertJsonPath('data.reason_code', 'incomplete');

        $this->actingAs($owner)->putJson($endpoint, [
            'rating' => 'helpful',
            'reason_code' => 'outdated',
            'comment' => 'This must be removed for positive feedback.',
        ])->assertOk()
            ->assertJsonPath('data.rating', 'helpful')
            ->assertJsonPath('data.reason_code', null)
            ->assertJsonPath('data.comment', null);

        $this->assertDatabaseCount('answer_feedback', 1);
        $this->assertDatabaseCount('answer_feedback_audit_events', 2);
        $this->assertDatabaseHas('answer_feedback_audit_events', ['event_type' => 'feedback_created']);
        $this->assertDatabaseHas('answer_feedback_audit_events', ['event_type' => 'feedback_updated']);

        $other = User::factory()->create();
        $this->actingAs($other)->putJson($endpoint, ['rating' => 'not_helpful'])->assertNotFound();
    }

    public function test_negative_feedback_appears_as_an_automatic_potential_gap_signal(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $conversation = app(CreateConversation::class)->handle($workspace, $owner);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $owner, 'What is the annual leave entitlement?', (string) Str::uuid());
        $assistant = Message::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'ordinal' => 2,
            'role' => MessageRole::Assistant,
            'kind' => MessageKind::GroundedAnswer,
            'display_text' => 'The available evidence did not establish that.',
            'created_by_user_id' => null,
            'in_reply_to_message_id' => $run->user_message_id,
            'submission_idempotency_key' => null,
        ]);
        $run->update(['assistant_message_id' => $assistant->id, 'status' => GenerationRunStatus::Completed, 'completed_at' => now()]);

        $this->actingAs($owner)->putJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}/runs/{$run->public_id}/feedback", [
            'rating' => 'not_helpful', 'reason_code' => 'incomplete',
        ])->assertOk();
        $this->actingAs($owner)->putJson("/api/workspaces/{$workspace->public_id}/question-review/policy", [
            'enabled' => true, 'include_retrospective' => true, 'retrospective_days' => 1, 'privacy_confirmation' => true,
        ])->assertOk();
        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/question-review/subjects", [
            'name' => 'Annual leave', 'priority' => 'normal',
        ])->assertCreated();

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/question-review/potential-gaps")
            ->assertOk()
            ->assertJsonPath('data.0.suggested_subject', 'Annual leave')
            ->assertJsonPath('data.0.negative_feedback_count', 1)
            ->assertJsonPath('data.0.signal_strength', 'early')
            ->assertJsonPath('meta.technical_failures_excluded', true);
    }
}
