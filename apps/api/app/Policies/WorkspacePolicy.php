<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function uploadDocuments(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->whereBelongsTo($user)
            ->exists();
    }
}
