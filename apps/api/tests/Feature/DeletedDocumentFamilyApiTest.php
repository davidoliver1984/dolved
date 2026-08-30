<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentFamilyDeletionStatus;
use App\Enums\DocumentGovernanceActorType;
use App\Enums\DocumentGovernanceTargetScope;
use App\Models\DocumentFamily;
use App\Models\DocumentFamilyDeletionOperation;
use App\Models\DocumentGovernanceAuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DeletedDocumentFamilyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_reads_tombstone_history_with_truthful_reason_date_actor_and_audit_reference(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['name' => 'Medication procedure', 'owner_user_id' => $owner->id]);
        $operation = $this->completedOperation($workspace, $family, $owner);
        $audit = DocumentGovernanceAuditEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'document_family_id' => $family->id,
            'document_id' => null,
            'target_scope' => DocumentGovernanceTargetScope::Family,
            'actor_type' => DocumentGovernanceActorType::Human,
            'actor_user_id' => $owner->id,
            'system_actor_code' => null,
            'action' => 'family_deletion_confirmed',
            'reason' => 'Replaced by the regional procedure.',
            'previous_values' => [],
            'new_values' => ['operation_public_id' => $operation->public_id],
            'occurred_at' => now()->subMinute(),
        ]);

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspace->public_id}/document-family-tombstones")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.family.name', 'Medication procedure')
            ->assertJsonPath('data.0.reason', 'Replaced by the regional procedure.')
            ->assertJsonPath('data.0.audit_reference', $audit->public_id)
            ->assertJsonPath('data.0.requested_by.name', $owner->name)
            ->assertJsonPath('data.0.versions_removed', 2)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_history_is_owner_admin_only_and_cross_workspace_is_concealed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $this->completedOperation($workspace, $family, $owner);
        $member = User::factory()->create();
        WorkspaceMembership::factory()->member()->for($workspace)->for($member)->create();
        $admin = User::factory()->create();
        WorkspaceMembership::factory()->admin()->for($workspace)->for($admin)->create();
        $otherOwner = User::factory()->create();
        Workspace::factory()->withOwner($otherOwner)->create();

        $this->actingAs($admin)
            ->getJson("/api/workspaces/{$workspace->public_id}/document-family-tombstones")
            ->assertOk();
        $this->actingAs($member)
            ->getJson("/api/workspaces/{$workspace->public_id}/document-family-tombstones")
            ->assertForbidden();
        $this->actingAs($otherOwner)
            ->getJson("/api/workspaces/{$workspace->public_id}/document-family-tombstones")
            ->assertNotFound();
    }

    private function completedOperation(Workspace $workspace, DocumentFamily $family, User $actor): DocumentFamilyDeletionOperation
    {
        $operation = DocumentFamilyDeletionOperation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'document_family_id' => $family->id,
            'requested_by_user_id' => $actor->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => DocumentFamilyDeletionStatus::Completed,
            'confirmation_state_digest' => str_repeat('a', 64),
            'version_snapshot' => [],
            'child_count' => 2,
            'completed_at' => now(),
        ]);
        $family->forceFill(['tombstoned_at' => now()])->save();

        return $operation;
    }
}
