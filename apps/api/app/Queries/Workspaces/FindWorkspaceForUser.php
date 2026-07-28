<?php

namespace App\Queries\Workspaces;

use App\Models\User;
use App\Models\WorkspaceMembership;

class FindWorkspaceForUser
{
    public function handle(User $user, string $publicId): WorkspaceMembership
    {
        return WorkspaceMembership::query()
            ->with('workspace')
            ->whereBelongsTo($user)
            ->whereHas(
                'workspace',
                fn ($query) => $query->where('public_id', $publicId),
            )
            ->firstOrFail();
    }
}
