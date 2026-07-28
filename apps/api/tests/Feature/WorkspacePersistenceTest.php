<?php

namespace Tests\Feature;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class WorkspacePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_creation_persists_the_workspace_and_owner_membership_atomically(): void
    {
        $creator = User::factory()->create();

        $workspace = $this->createWorkspace()->handle($creator, '  Acme Research  ');

        $this->assertSame('Acme Research', $workspace->name);
        $this->assertSame('acme-research', $workspace->slug);
        $this->assertTrue(Str::isUuid($workspace->public_id));
        $this->assertTrue($workspace->creator->is($creator));

        $membership = $workspace->memberships->sole();

        $this->assertTrue($membership->user->is($creator));
        $this->assertSame(WorkspaceRole::Owner, $membership->role);
        $this->assertInstanceOf(CarbonInterface::class, $membership->joined_at);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('workspace_memberships', 1);
    }

    public function test_workspace_creation_rolls_back_if_owner_membership_creation_fails(): void
    {
        $creator = User::factory()->create();
        $eventName = 'eloquent.creating: '.WorkspaceMembership::class;

        Event::listen($eventName, function (): never {
            throw new RuntimeException('Simulated membership failure.');
        });

        try {
            $this->createWorkspace()->handle($creator, 'Rollback Workspace');
            $this->fail('The simulated membership failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated membership failure.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertDatabaseMissing('workspaces', ['name' => 'Rollback Workspace']);
        $this->assertDatabaseCount('workspace_memberships', 0);
    }

    public function test_membership_factory_states_are_cast_to_workspace_role_enum(): void
    {
        $owner = WorkspaceMembership::factory()->owner()->create();
        $admin = WorkspaceMembership::factory()->admin()->create();
        $member = WorkspaceMembership::factory()->member()->create();

        $this->assertSame(WorkspaceRole::Owner, $owner->role);
        $this->assertSame(WorkspaceRole::Admin, $admin->role);
        $this->assertSame(WorkspaceRole::Member, $member->role);
        $this->assertInstanceOf(CarbonInterface::class, $member->joined_at);
    }

    public function test_workspace_membership_and_user_relationships_are_available(): void
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->createWorkspace()->handle($creator, 'Relationship Workspace');
        $membership = WorkspaceMembership::factory()
            ->for($workspace)
            ->for($member)
            ->admin()
            ->create();

        $this->assertTrue($workspace->fresh()->creator->is($creator));
        $this->assertTrue($workspace->fresh()->memberships->contains($membership));
        $this->assertTrue($workspace->fresh()->members->contains($creator));
        $this->assertTrue($workspace->fresh()->members->contains($member));
        $this->assertTrue($membership->workspace->is($workspace));
        $this->assertTrue($membership->user->is($member));
        $this->assertTrue($creator->fresh()->createdWorkspaces->contains($workspace));
        $this->assertTrue($member->fresh()->workspaceMemberships->contains($membership));
        $this->assertTrue($member->fresh()->workspaces->contains($workspace));
    }

    public function test_duplicate_membership_is_rejected_by_the_database(): void
    {
        $creator = User::factory()->create();
        $workspace = $this->createWorkspace()->handle($creator, 'Unique Membership');

        $this->expectException(QueryException::class);

        WorkspaceMembership::factory()
            ->for($workspace)
            ->for($creator)
            ->member()
            ->create();
    }

    public function test_same_user_can_belong_to_multiple_workspaces(): void
    {
        $creator = User::factory()->create();

        $first = $this->createWorkspace()->handle($creator, 'Shared Name');
        $second = $this->createWorkspace()->handle($creator, 'Shared Name');

        $this->assertSame('shared-name', $first->slug);
        $this->assertSame('shared-name-2', $second->slug);
        $this->assertCount(2, $creator->fresh()->workspaceMemberships);
        $this->assertCount(2, $creator->fresh()->workspaces);
    }

    public function test_different_users_can_belong_to_one_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->createWorkspace()->handle($owner, 'Collaborative Workspace');

        WorkspaceMembership::factory()
            ->for($workspace)
            ->for($member)
            ->member()
            ->create();

        $this->assertCount(2, $workspace->fresh()->memberships);
        $this->assertTrue($workspace->fresh()->members->contains($owner));
        $this->assertTrue($workspace->fresh()->members->contains($member));
    }

    public function test_invalid_role_is_rejected_by_the_database(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'super-admin',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_second_owner_is_rejected_by_the_database(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $workspace = $this->createWorkspace()->handle($firstOwner, 'Single Owner');

        $this->expectException(QueryException::class);

        WorkspaceMembership::factory()
            ->for($workspace)
            ->for($secondOwner)
            ->owner()
            ->create();
    }

    public function test_public_id_is_unique_and_generated_for_each_workspace(): void
    {
        $creator = User::factory()->create();
        $workspace = $this->createWorkspace()->handle($creator, 'Public Identity');

        $this->assertTrue(Str::isUuid($workspace->public_id));

        $this->expectException(QueryException::class);

        Workspace::factory()->create([
            'public_id' => $workspace->public_id,
        ]);
    }

    public function test_public_id_cannot_be_changed_through_the_model(): void
    {
        $workspace = Workspace::factory()->create();
        $workspace->public_id = (string) Str::uuid();

        $this->expectException(LogicException::class);

        $workspace->save();
    }

    public function test_foreign_keys_prevent_deleting_a_referenced_user(): void
    {
        $creator = User::factory()->create();
        $this->createWorkspace()->handle($creator, 'Restricted User');

        $this->expectException(QueryException::class);

        $creator->delete();
    }

    public function test_deleting_a_workspace_removes_its_memberships(): void
    {
        $creator = User::factory()->create();
        $workspace = $this->createWorkspace()->handle($creator, 'Cascading Workspace');
        $membershipId = $workspace->memberships->sole()->id;

        $workspace->delete();

        $this->assertDatabaseMissing('workspace_memberships', ['id' => $membershipId]);
    }

    public function test_clean_migrations_create_the_required_workspace_schema(): void
    {
        $this->assertTrue(Schema::hasColumns('workspaces', [
            'id',
            'public_id',
            'name',
            'slug',
            'created_by_user_id',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('workspace_memberships', [
            'id',
            'workspace_id',
            'user_id',
            'role',
            'joined_at',
            'created_at',
            'updated_at',
        ]));

        $membershipIndexes = collect(Schema::getIndexes('workspace_memberships'))
            ->pluck('name');

        $this->assertTrue(
            $membershipIndexes->contains('workspace_memberships_workspace_user_unique')
        );
        $this->assertTrue(
            $membershipIndexes->contains('workspace_memberships_one_owner_per_workspace')
        );
    }

    private function createWorkspace(): CreateWorkspace
    {
        return $this->app->make(CreateWorkspace::class);
    }
}
