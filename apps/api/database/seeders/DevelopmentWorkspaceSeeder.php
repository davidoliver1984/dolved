<?php

namespace Database\Seeders;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentWorkspaceSeeder extends Seeder
{
    /**
     * Seed deterministic, synthetic workspace fixtures for local development.
     */
    public function run(CreateWorkspace $createWorkspace): void
    {
        $primaryUser = User::query()->updateOrCreate(
            ['email' => 'workspace.tester@example.test'],
            [
                'name' => 'Workspace Tester',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $secondaryUser = User::query()->updateOrCreate(
            ['email' => 'workspace.owner@example.test'],
            [
                'name' => 'Workspace Owner',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $primaryWorkspace = $this->workspace(
            $createWorkspace,
            $primaryUser,
            'Atlas Research',
            'atlas-research',
        );

        $secondaryWorkspace = $this->workspace(
            $createWorkspace,
            $secondaryUser,
            'Beacon Operations',
            'beacon-operations',
        );

        WorkspaceMembership::query()->updateOrCreate(
            [
                'workspace_id' => $secondaryWorkspace->id,
                'user_id' => $primaryUser->id,
            ],
            [
                'role' => WorkspaceRole::Admin,
                'joined_at' => now(),
            ],
        );

        WorkspaceMembership::query()->updateOrCreate(
            [
                'workspace_id' => $primaryWorkspace->id,
                'user_id' => $secondaryUser->id,
            ],
            [
                'role' => WorkspaceRole::Member,
                'joined_at' => now(),
            ],
        );
    }

    private function workspace(
        CreateWorkspace $createWorkspace,
        User $owner,
        string $name,
        string $slug,
    ): Workspace {
        return Workspace::query()->where('slug', $slug)->first()
            ?? $createWorkspace->handle($owner, $name);
    }
}
