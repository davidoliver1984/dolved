<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WorkspaceInvitationStatus;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAdministrationAuditEvent;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_and_admin_can_view_membership_administration_without_cross_workspace_leakage(): void
    {
        [$workspace, $owner, $admin, $member] = $this->workspaceWithRoles();
        $other = Workspace::factory()->withOwner()->create();

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/members")
            ->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($admin)->getJson("/api/workspaces/{$workspace->public_id}/members")
            ->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($member->user)->getJson("/api/workspaces/{$workspace->public_id}/members")
            ->assertForbidden();
        $this->actingAs($owner)->getJson("/api/workspaces/{$other->public_id}/members")
            ->assertNotFound();
    }

    public function test_invitation_is_normalized_digest_only_reissued_and_one_time_link_is_not_recoverable_on_replay(): void
    {
        Notification::fake();
        [$workspace, $owner] = $this->workspaceWithRoles();
        $key = (string) Str::uuid();
        $path = "/api/workspaces/{$workspace->public_id}/invitations";

        $first = $this->actingAs($owner)->postJson($path, [
            'email' => '  New.Member@Example.TEST ',
            'role' => 'member',
            'idempotency_key' => $key,
        ])->assertCreated()
            ->assertJsonPath('data.link_returned_once', true)
            ->assertJsonPath('data.delivery_status', 'sent');
        $link = $first->json('data.invitation_link');
        $token = basename((string) $link);
        $invitation = WorkspaceInvitation::query()->sole();
        $this->assertSame('new.member@example.test', $invitation->invited_email);
        $this->assertSame(hash('sha256', $token), $invitation->token_digest);
        $this->assertDatabaseMissing('workspace_invitations', ['token_digest' => $token]);
        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);

        $this->actingAs($owner)->postJson($path, [
            'email' => 'new.member@example.test',
            'role' => 'member',
            'idempotency_key' => $key,
        ])->assertOk()
            ->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.invitation_link', null)
            ->assertJsonPath('data.link_returned_once', false);

        $this->actingAs($owner)->postJson($path, [
            'email' => 'new.member@example.test',
            'role' => 'member',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertSame(WorkspaceInvitationStatus::Revoked, $invitation->fresh()->status);
        $this->assertSame(1, WorkspaceInvitation::query()->where('status', WorkspaceInvitationStatus::Pending)->count());
        $this->assertStringNotContainsString($token, WorkspaceAdministrationAuditEvent::query()->get()->toJson());
    }

    public function test_admin_can_invite_and_remove_only_ordinary_members(): void
    {
        Notification::fake();
        [$workspace, $owner, $admin, $member] = $this->workspaceWithRoles();
        $base = "/api/workspaces/{$workspace->public_id}";

        $this->actingAs($admin)->postJson("{$base}/invitations", [
            'email' => 'ordinary@example.test',
            'role' => 'member',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $this->actingAs($admin)->postJson("{$base}/invitations", [
            'email' => 'administrator@example.test',
            'role' => 'admin',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict()->assertJsonPath('error.code', 'invitation_role_forbidden');
        $this->assertDatabaseHas('workspace_administration_audit_events', [
            'workspace_id' => $workspace->id,
            'action' => 'administration_failed',
        ]);
        $this->actingAs($admin)->deleteJson("{$base}/memberships/{$member->public_id}", [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $this->actingAs($admin)->deleteJson("{$base}/memberships/{$owner->workspaceMemberships()->where('workspace_id', $workspace->id)->value('public_id')}", [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict()->assertJsonPath('error.code', 'member_removal_forbidden');
    }

    public function test_delivery_failure_does_not_invalidate_an_already_issued_invitation(): void
    {
        [$workspace, $owner] = $this->workspaceWithRoles();
        config(['mail.default' => 'unconfigured-test-transport']);

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/invitations", [
            'email' => 'delivery-failure@example.test',
            'role' => 'member',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.delivery_status', 'unavailable')
            ->assertJsonPath('data.link_returned_once', true);

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'invited_email' => 'delivery-failure@example.test',
            'status' => WorkspaceInvitationStatus::Pending->value,
        ]);
    }

    public function test_invitation_acceptance_requires_the_matching_verified_email_and_materializes_expiry(): void
    {
        [$workspace, $owner] = $this->workspaceWithRoles();
        $token = str_repeat('a', 64);
        $invited = User::factory()->create(['email' => 'invitee@example.test']);
        $wrong = User::factory()->create(['email' => 'wrong@example.test']);
        $invitation = $this->invitation($workspace, $owner, $token, 'invitee@example.test');

        $this->actingAs($wrong)->postJson('/api/workspace-invitations/accept', ['token' => $token])
            ->assertNotFound()->assertJsonPath('error.code', 'invitation_unavailable');
        $this->actingAs($invited)->postJson('/api/workspace-invitations/accept', ['token' => $token])
            ->assertOk()->assertJsonPath('data.role', 'member');
        $this->assertSame(WorkspaceInvitationStatus::Accepted, $invitation->fresh()->status);
        $this->assertDatabaseHas('workspace_memberships', ['workspace_id' => $workspace->id, 'user_id' => $invited->id]);

        $expiredToken = str_repeat('b', 64);
        $expiredUser = User::factory()->create(['email' => 'expired@example.test']);
        $expired = $this->invitation($workspace, $owner, $expiredToken, 'expired@example.test', now()->subMinute());
        $this->actingAs($expiredUser)->postJson('/api/workspace-invitations/accept', ['token' => $expiredToken])
            ->assertNotFound()->assertJsonPath('error.code', 'invitation_unavailable');
        $this->assertSame(WorkspaceInvitationStatus::Expired, $expired->fresh()->status);
    }

    public function test_expired_invitations_are_materialized_and_audited_by_the_normal_scheduler_command(): void
    {
        [$workspace, $owner] = $this->workspaceWithRoles();
        $invitation = $this->invitation(
            $workspace,
            $owner,
            str_repeat('c', 64),
            'expired-scheduled@example.test',
            now()->subMinute(),
        );

        $this->artisan('workspace-invitations:expire')->assertSuccessful();

        $this->assertSame(WorkspaceInvitationStatus::Expired, $invitation->fresh()->status);
        $this->assertDatabaseHas('workspace_administration_audit_events', [
            'workspace_id' => $workspace->id,
            'action' => 'invitation_expired',
            'target_public_id' => $invitation->public_id,
        ]);
    }

    public function test_database_constraints_reject_a_second_pending_invitation_for_the_same_normalized_identity(): void
    {
        [$workspace, $owner] = $this->workspaceWithRoles();
        $this->invitation($workspace, $owner, str_repeat('d', 64), 'same@example.test');

        $this->expectException(QueryException::class);
        $this->invitation($workspace, $owner, str_repeat('e', 64), 'same@example.test');
    }

    public function test_owner_role_changes_and_transfer_are_owner_only_atomic_and_repeat_safe(): void
    {
        [$workspace, $owner, $admin, $member] = $this->workspaceWithRoles();
        $base = "/api/workspaces/{$workspace->public_id}";

        $this->actingAs($admin)->patchJson("{$base}/memberships/{$member->public_id}/role", [
            'role' => 'admin', 'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict()->assertJsonPath('error.code', 'role_change_forbidden');

        $key = (string) Str::uuid();
        $this->actingAs($owner)->postJson("{$base}/memberships/{$member->public_id}/ownership-transfers", [
            'idempotency_key' => $key,
        ])->assertOk();
        $this->assertSame(1, WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('role', WorkspaceRole::Owner)->count());
        $this->assertSame(WorkspaceRole::Admin, $owner->workspaceMemberships()->where('workspace_id', $workspace->id)->firstOrFail()->role);
        $this->assertSame(WorkspaceRole::Owner, $member->fresh()->role);

        $this->actingAs($owner)->postJson("{$base}/memberships/{$member->public_id}/ownership-transfers", [
            'idempotency_key' => $key,
        ])->assertOk();
        $this->assertSame(1, WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('role', WorkspaceRole::Owner)->count());
    }

    public function test_owner_must_transfer_while_other_members_can_leave_and_workspace_content_remains(): void
    {
        [$workspace, $owner, $admin, $member] = $this->workspaceWithRoles();
        $document = Document::factory()->for($workspace)->for($owner, 'createdBy')->create();
        $path = "/api/workspaces/{$workspace->public_id}/membership";

        $this->actingAs($owner)->deleteJson($path)
            ->assertConflict()->assertJsonPath('error.code', 'owner_must_transfer');
        $this->actingAs($admin)->deleteJson($path)->assertOk();
        $this->actingAs($member->user)->deleteJson($path)->assertOk();

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'workspace_id' => $workspace->id]);
    }

    /** @return array{Workspace, User, User, WorkspaceMembership} */
    private function workspaceWithRoles(): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $memberUser = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        WorkspaceMembership::factory()->for($workspace)->for($admin)->admin()->create();
        $member = WorkspaceMembership::factory()->for($workspace)->for($memberUser)->member()->create();

        return [$workspace, $owner, $admin, $member];
    }

    private function invitation(Workspace $workspace, User $owner, string $token, string $email, mixed $expiresAt = null): WorkspaceInvitation
    {
        return WorkspaceInvitation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'invited_email' => $email,
            'intended_role' => WorkspaceRole::Member,
            'invited_by_user_id' => $owner->id,
            'token_digest' => hash('sha256', $token),
            'status' => WorkspaceInvitationStatus::Pending,
            'expires_at' => $expiresAt ?? now()->addDay(),
        ]);
    }
}
