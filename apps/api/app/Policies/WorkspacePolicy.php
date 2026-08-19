<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAdministration(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->whereBelongsTo($user)
            ->whereIn('role', [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])
            ->exists();
    }

    public function uploadDocuments(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->whereBelongsTo($user)
            ->exists();
    }
}
