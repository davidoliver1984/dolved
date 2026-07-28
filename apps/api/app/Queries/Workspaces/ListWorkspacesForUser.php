<?php

namespace App\Queries\Workspaces;

use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Collection;

class ListWorkspacesForUser
{
    /**
     * @return Collection<int, WorkspaceMembership>
     */
    public function handle(User $user): Collection
    {
        return WorkspaceMembership::query()
            ->with('workspace')
            ->whereBelongsTo($user)
            ->join('workspaces', 'workspaces.id', '=', 'workspace_memberships.workspace_id')
            ->orderBy('workspaces.name')
            ->select('workspace_memberships.*')
            ->get();
    }
}
