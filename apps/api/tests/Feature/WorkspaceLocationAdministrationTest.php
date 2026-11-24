<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAdministrationAuditEvent;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkspaceLocationAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_multiple_hierarchical_locations(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();

        $region = $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/locations", [
            'name' => 'South West',
            'kind' => 'region',
            'parent_public_id' => null,
        ])->assertCreated()->json('data');

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/locations", [
            'name' => 'Meadow Court',
            'kind' => 'site',
            'parent_public_id' => $region['public_id'],
        ])->assertCreated()->assertJsonPath('data.parent.public_id', $region['public_id']);

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/locations")
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_member_cannot_use_location_administration_and_foreign_parent_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();

        $this->actingAs($member)->getJson("/api/workspaces/{$workspace->public_id}/locations")->assertForbidden();

        $otherOwner = User::factory()->create();
        $otherWorkspace = Workspace::factory()->withOwner($otherOwner)->create();
        $foreignParent = OrganisationalLocation::factory()->for($otherWorkspace)->create();

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/locations", [
            'name' => 'Wrongly nested site',
            'kind' => 'site',
            'parent_public_id' => $foreignParent->public_id,
        ])->assertUnprocessable();
    }

    public function test_owner_can_retire_location_without_deleting_its_identity_or_history(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $location = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Riverside House']);

        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$workspace->public_id}/locations/{$location->public_id}/retire")
            ->assertOk()
            ->assertJsonPath('data.status', 'retired')
            ->assertJsonPath('data.capabilities.retire', false);

        $this->assertDatabaseHas('organisational_locations', [
            'id' => $location->id,
            'public_id' => $location->public_id,
        ]);
        $this->assertNotNull($location->refresh()->retired_at);
        $this->assertDatabaseHas('workspace_administration_audit_events', [
            'workspace_id' => $workspace->id,
            'action' => 'location_retired',
            'target_public_id' => $location->public_id,
        ]);
        $this->assertSame('retired', WorkspaceAdministrationAuditEvent::query()->latest('id')->firstOrFail()->after['status']);
        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspace->public_id}/document-metadata")
            ->assertOk()
            ->assertJsonMissing(['public_id' => $location->public_id]);
    }

    public function test_parent_location_cannot_be_retired_until_active_children_are_retired(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $parent = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'South West']);
        $child = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Meadow Court', 'parent_id' => $parent->id]);

        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$workspace->public_id}/locations/{$parent->public_id}/retire")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location');

        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$workspace->public_id}/locations/{$child->public_id}/retire")
            ->assertOk();
        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$workspace->public_id}/locations/{$parent->public_id}/retire")
            ->assertOk();
    }

    public function test_member_cannot_retire_a_location(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $location = OrganisationalLocation::factory()->for($workspace)->create();

        $this->actingAs($member)
            ->patchJson("/api/workspaces/{$workspace->public_id}/locations/{$location->public_id}/retire")
            ->assertForbidden();
    }
}
