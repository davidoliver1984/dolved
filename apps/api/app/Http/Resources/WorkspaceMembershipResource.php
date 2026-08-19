<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\WorkspaceRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actorRole = $request->attributes->get('workspace_role')
            ?? $this->workspace->memberships()->where('user_id', $request->user()?->id)->value('role');

        return [
            'public_id' => $this->public_id,
            'user' => [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'role' => $this->role->value,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'capabilities' => [
                'change_role' => $actorRole === WorkspaceRole::Owner->value
                    && $this->role !== WorkspaceRole::Owner,
                'remove' => $this->user_id !== $request->user()?->id && match ($actorRole) {
                    WorkspaceRole::Owner->value => $this->role !== WorkspaceRole::Owner,
                    WorkspaceRole::Admin->value => $this->role === WorkspaceRole::Member,
                    default => false,
                },
                'transfer_ownership' => $actorRole === WorkspaceRole::Owner->value
                    && $this->role !== WorkspaceRole::Owner,
            ],
        ];
    }
}
