<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuestionReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_off_by_default_and_only_owner_can_enable_it(): void
    {
        [$owner, $workspace] = $this->workspaceFixture();
        $admin = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($admin)->admin()->create();

        $this->actingAs($admin)->putJson("/api/workspaces/{$workspace->public_id}/question-review/policy", [
            'enabled' => true, 'include_retrospective' => false, 'retrospective_days' => 0, 'privacy_confirmation' => true,
        ])->assertForbidden();

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/question-review/policy")
            ->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.retrospective_default', 'off');

        $this->actingAs($owner)->putJson("/api/workspaces/{$workspace->public_id}/question-review/policy", [
            'enabled' => true, 'include_retrospective' => true, 'retrospective_days' => 30, 'privacy_confirmation' => true,
        ])->assertOk()->assertJsonPath('data.enabled', true)->assertJsonPath('data.retrospective_days', 30);

        $this->assertDatabaseHas('question_review_audit_events', ['workspace_id' => $workspace->id, 'event_type' => 'policy_enabled']);
    }

    public function test_list_hides_content_and_owner_content_read_is_audited(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->workspaceFixture();
        $conversation = app(CreateConversation::class)->handle($workspace, $owner);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $owner, 'Sensitive synthetic question', (string) Str::uuid());

        $this->actingAs($owner)->putJson("/api/workspaces/{$workspace->public_id}/question-review/policy", [
            'enabled' => true, 'include_retrospective' => true, 'retrospective_days' => 1, 'privacy_confirmation' => true,
        ])->assertOk();

        $list = $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/question-review/questions")
            ->assertOk()->assertJsonMissing(['question' => 'Sensitive synthetic question'])
            ->assertJsonPath('meta.content_hidden_by_default', true);
        $this->assertSame($run->public_id, $list->json('data.0.run_id'));

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/question-review/questions/{$run->public_id}?purpose=Quality%20review")
            ->assertOk()->assertJsonPath('data.question', 'Sensitive synthetic question');
        $this->assertDatabaseHas('question_review_audit_events', [
            'workspace_id' => $workspace->id, 'generation_run_id' => $run->id, 'event_type' => 'content_viewed', 'purpose' => 'Quality review',
        ]);
    }

    public function test_human_review_labels_and_subjects_create_a_knowledge_gap_record(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->workspaceFixture();
        $conversation = app(CreateConversation::class)->handle($workspace, $owner);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $owner, 'Where is the policy?', (string) Str::uuid());
        $this->actingAs($owner)->putJson("/api/workspaces/{$workspace->public_id}/question-review/policy", [
            'enabled' => true, 'include_retrospective' => true, 'retrospective_days' => 1, 'privacy_confirmation' => true,
        ])->assertOk();
        $subject = $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/question-review/subjects", [
            'name' => 'Personal devices', 'priority' => 'high',
        ])->assertCreated()->json('data');

        $this->actingAs($owner)->patchJson("/api/workspaces/{$workspace->public_id}/question-review/questions/{$run->public_id}", [
            'review_status' => 'missing_evidence',
            'issue_type' => 'missing_content',
            'workflow_state' => 'confirmed',
            'priority' => 'high',
            'owner_user_id' => null,
            'review_notes' => 'Confirmed against the authorised corpus.',
            'linked_document_public_id' => null,
            'subject_ids' => [$subject['public_id']],
        ])->assertOk()->assertJsonPath('data.issue_type', 'missing_content');

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/question-review/knowledge-gaps")
            ->assertOk()->assertJsonPath('data.0.name', 'Personal devices')->assertJsonPath('data.0.confirmed_gap_count', 1);
    }

    private function workspaceFixture(): array
    {
        $owner = User::factory()->create();

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }
}
