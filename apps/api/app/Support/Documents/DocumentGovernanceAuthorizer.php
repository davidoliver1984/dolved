<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Auth\Access\AuthorizationException;

final class DocumentGovernanceAuthorizer
{
    public function ordinary(User $actor, Document $document): void
    {
        $role = $this->role($actor, $document);

        if (! in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true)) {
            throw new AuthorizationException('Only workspace owners and administrators may govern documents.');
        }
    }

    public function historicalCorrection(User $actor, Document $document): void
    {
        if ($this->role($actor, $document) !== WorkspaceRole::Owner) {
            throw new AuthorizationException('Only workspace owners may correct historical governance facts.');
        }
    }

    private function role(User $actor, Document $document): ?WorkspaceRole
    {
        $role = WorkspaceMembership::query()
            ->where('workspace_id', $document->workspace_id)
            ->where('user_id', $actor->id)
            ->value('role');

        if ($role instanceof WorkspaceRole) {
            return $role;
        }

        return is_string($role) ? WorkspaceRole::tryFrom($role) : null;
    }
}
