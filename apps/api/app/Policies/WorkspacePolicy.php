<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkspacePolicy
{
    public function viewAdministration(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->whereBelongsTo($user)
            ->whereIn('role', [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])
            ->exists();
    }

    public function viewUsage(User $user, Workspace $workspace): bool
    {
        return $this->viewAdministration($user, $workspace);
    }

    public function manageQuestionReview(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->whereBelongsTo($user)
            ->where('role', WorkspaceRole::Owner->value)
            ->exists();
    }

    public function reviewQuestions(User $user, Workspace $workspace): bool
    {
        if (! $workspace->questionReviewPolicy()->where('enabled', true)->exists()) {
            return false;
        }

        if ($this->manageQuestionReview($user, $workspace)) {
            return true;
        }

        return DB::table('workspace_question_review_grants')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('capability', 'review')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function uploadDocuments(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->whereBelongsTo($user)
            ->exists();
    }

    public function viewDocumentMetadata(User $user, Workspace $workspace): bool
    {
        return $this->uploadDocuments($user, $workspace);
    }

    public function manageDocumentMetadata(User $user, Workspace $workspace): bool
    {
        return $this->viewAdministration($user, $workspace);
    }

    public function manageDocumentGovernance(User $user, Workspace $workspace): bool
    {
        return $this->viewAdministration($user, $workspace);
    }

    public function correctDocumentGovernance(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->whereBelongsTo($user)
            ->where('role', WorkspaceRole::Owner->value)
            ->exists();
    }
}
