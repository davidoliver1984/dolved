<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentGovernanceEventKey;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceNotification;
use App\Models\ImportBatch;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentGovernanceInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_is_recipient_scoped_and_resolves_only_live_authorised_targets(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $other = User::factory()->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $visible = $this->notification($owner, $workspace, [
            'event_key' => DocumentGovernanceEventKey::GovernanceReviewDueSoon,
            'severity' => 'action_required',
            'target_kind' => 'family',
            'target_public_id' => $family->public_id,
            'target_display_label' => $family->name,
        ]);
        $this->notification($other, $workspace);

        $this->actingAs($owner)
            ->getJson($this->url($workspace))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $visible->public_id)
            ->assertJsonPath('data.0.title', 'Review due soon')
            ->assertJsonPath('data.0.target_route', "/app/workspaces/{$workspace->public_id}/documents/families/{$family->public_id}")
            ->assertJsonPath('meta.unread_count', 1);

        $family->forceFill(['tombstoned_at' => now()])->save();
        $this->actingAs($owner)->getJson($this->url($workspace))
            ->assertOk()->assertJsonPath('data.0.target_route', null);
    }

    public function test_import_batch_notification_resolves_to_the_exact_batch_detail(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $batch = ImportBatch::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'initiated_by_user_id' => $owner->id,
            'status' => 'open',
            'retention_expires_at' => now()->addDays(7),
        ]);
        $this->notification($owner, $workspace, [
            'event_key' => DocumentGovernanceEventKey::ImportBatchCompletedWithExceptions,
            'template_key' => DocumentGovernanceEventKey::ImportBatchCompletedWithExceptions->value,
            'target_kind' => 'import_batch',
            'target_public_id' => $batch->public_id,
        ]);

        $this->actingAs($owner)->getJson($this->url($workspace))
            ->assertOk()
            ->assertJsonPath(
                'data.0.target_route',
                "/app/workspaces/{$workspace->public_id}/documents/imports?batch={$batch->public_id}",
            );
    }

    public function test_read_and_dismiss_are_idempotent_and_do_not_change_actionable_work(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'review_due_date' => today()->addDays(7),
        ]);
        $notification = $this->notification($owner, $workspace, [
            'event_key' => DocumentGovernanceEventKey::GovernanceReviewDueSoon,
            'severity' => 'action_required',
            'target_kind' => 'family',
            'target_public_id' => $family->public_id,
        ]);

        $read = "/api/workspaces/{$workspace->public_id}/governance-notifications/{$notification->public_id}/read";
        $dismiss = "/api/workspaces/{$workspace->public_id}/governance-notifications/{$notification->public_id}/dismiss";
        $this->actingAs($owner)->postJson($read)->assertOk();
        $readAt = $notification->refresh()->read_at;
        $this->actingAs($owner)->postJson($read)->assertOk();
        $this->assertTrue($readAt->equalTo($notification->refresh()->read_at));

        $this->actingAs($owner)->postJson($dismiss)->assertOk();
        $dismissedAt = $notification->refresh()->dismissed_at;
        $this->actingAs($owner)->postJson($dismiss)->assertOk();
        $this->assertTrue($dismissedAt->equalTo($notification->refresh()->dismissed_at));

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspace->public_id}/governance-actionable-work")
            ->assertOk()
            ->assertJsonPath('data.review_due_soon', 1);
        $this->actingAs($owner)->getJson($this->url($workspace))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson($this->url($workspace).'?history=true')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_actionable_work_uses_live_domain_state_and_current_role(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'review_due_date' => today()->subDay(),
        ]);
        Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Draft,
        ]);

        $url = "/api/workspaces/{$workspace->public_id}/governance-actionable-work";
        $this->actingAs($owner)->getJson($url)->assertOk()
            ->assertJsonPath('data.awaiting_approval', 1)
            ->assertJsonPath('data.review_overdue', 1);
        $this->actingAs($member)->getJson($url)->assertOk()
            ->assertJsonPath('data.awaiting_approval', 0)
            ->assertJsonPath('data.review_overdue', 1);

        $family->forceFill(['review_due_date' => null])->save();
        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('data.review_overdue', 0);
    }

    public function test_notification_endpoints_conceal_other_users_and_workspaces(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        [$otherOwner, $otherWorkspace] = $this->ownerWorkspace();
        $notification = $this->notification($otherOwner, $otherWorkspace);

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspace->public_id}/governance-notifications/{$notification->public_id}/read")
            ->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function notification(User $recipient, Workspace $workspace, array $overrides = []): DocumentGovernanceNotification
    {
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $recipient->id)
            ->first();
        if ($membership === null) {
            $membership = WorkspaceMembership::factory()->for($workspace)->for($recipient)->member()->create();
        }

        return DocumentGovernanceNotification::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'recipient_user_id' => $recipient->id,
            'recipient_user_public_id' => $recipient->public_id,
            'recipient_workspace_membership_id' => $membership->id,
            'event_key' => DocumentGovernanceEventKey::ImportBatchCompleted,
            'source_event_id' => (string) Str::uuid(),
            'template_key' => DocumentGovernanceEventKey::ImportBatchCompleted->value,
            'template_version' => 1,
            'parameters' => [],
            'severity' => 'info',
            'expires_at' => now()->addDays(90),
        ], $overrides));
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create();

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }

    private function url(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/governance-notifications";
    }
}
