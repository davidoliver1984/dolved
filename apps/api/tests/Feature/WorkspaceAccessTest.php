<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_the_isolated_in_memory_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database'),
        );
    }

    public function test_authenticated_verified_user_lists_only_assigned_workspaces(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $alpha = Workspace::factory()->withOwner($otherOwner)->create(['name' => 'Alpha']);
        $beta = Workspace::factory()->withOwner()->create(['name' => 'Beta']);
        $inaccessible = Workspace::factory()->withOwner()->create(['name' => 'Hidden']);

        WorkspaceMembership::factory()
            ->for($alpha)
            ->for($user)
            ->admin()
            ->create();
        WorkspaceMembership::factory()
            ->for($beta)
            ->for($user)
            ->member()
            ->create();

        $response = $this->actingAs($user)->getJson('/api/workspaces');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.0.role', WorkspaceRole::Admin->value)
            ->assertJsonPath('data.1.name', 'Beta')
            ->assertJsonPath('data.1.role', WorkspaceRole::Member->value)
            ->assertJsonMissing(['public_id' => $inaccessible->public_id]);
    }

    public function test_assigned_workspace_is_resolved_by_public_id(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withOwner()->create();
        WorkspaceMembership::factory()
            ->for($workspace)
            ->for($user)
            ->member()
            ->create();

        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'public_id' => $workspace->public_id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'role' => WorkspaceRole::Member->value,
                ],
            ]);
    }

    public function test_inaccessible_workspace_returns_not_found(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withOwner()->create();

        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->public_id}")
            ->assertNotFound();
    }

    public function test_workspace_endpoints_require_authentication_and_verification(): void
    {
        $workspace = Workspace::factory()->withOwner()->create();
        $unverifiedUser = User::factory()->unverified()->create();

        $this->getJson('/api/workspaces')->assertUnauthorized();

        $this->actingAs($unverifiedUser)
            ->getJson('/api/workspaces')
            ->assertForbidden();

        $this->actingAs($unverifiedUser)
            ->getJson("/api/workspaces/{$workspace->public_id}")
            ->assertForbidden();
    }

    public function test_development_workspace_seeding_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $primaryUser = User::query()
            ->where('email', 'workspace.tester@example.test')
            ->firstOrFail();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('workspaces', 2);
        $this->assertDatabaseCount('workspace_memberships', 4);
        $this->assertCount(2, $primaryUser->workspaceMemberships);
        $this->assertSame(
            [
                WorkspaceRole::Admin->value,
                WorkspaceRole::Owner->value,
            ],
            $primaryUser->workspaceMemberships()
                ->orderBy('role')
                ->pluck('role')
                ->map(fn (WorkspaceRole $role): string => $role->value)
                ->all(),
        );

        foreach (Workspace::query()->get() as $workspace) {
            $this->assertSame(
                1,
                $workspace->memberships()
                    ->where('role', WorkspaceRole::Owner)
                    ->count(),
            );
        }
    }
}
