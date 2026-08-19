<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $this->isActiveWorkspaceMember($user, $document);
    }

    public function retry(User $user, Document $document): bool
    {
        return $this->isAdministrator($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->isAdministrator($user, $document);
    }

    public function requestIngestion(User $user, Document $document): bool
    {
        return $this->isActiveWorkspaceMember($user, $document);
    }

    public function completeUpload(User $user, Document $document): bool
    {
        return $this->isActiveWorkspaceMember($user, $document);
    }

    private function isActiveWorkspaceMember(
        User $user,
        Document $document,
    ): bool {
        return $document->workspace
            ->memberships()
            ->whereBelongsTo($user)
            ->exists();
    }

    private function isAdministrator(User $user, Document $document): bool
    {
        return $document->workspace
            ->memberships()
            ->whereBelongsTo($user)
            ->whereIn('role', [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])
            ->exists();
    }
}
