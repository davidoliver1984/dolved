<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace
{
    public function handle(User $creator, string $name): Workspace
    {
        return DB::transaction(function () use ($creator, $name): Workspace {
            $workspace = new Workspace;
            $workspace->public_id = (string) Str::uuid();
            $workspace->name = trim($name);
            $workspace->slug = $this->uniqueSlug($name);
            $workspace->creator()->associate($creator);
            $workspace->save();

            $membership = new WorkspaceMembership([
                'public_id' => (string) Str::uuid(),
                'role' => WorkspaceRole::Owner,
                'joined_at' => now(),
            ]);
            $membership->workspace()->associate($workspace);
            $membership->user()->associate($creator);
            $membership->save();

            return $workspace->load(['creator', 'memberships.user']);
        });
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'workspace';
        $slug = $baseSlug;
        $suffix = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
