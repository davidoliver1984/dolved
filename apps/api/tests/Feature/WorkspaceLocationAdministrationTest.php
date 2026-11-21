<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
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
}
