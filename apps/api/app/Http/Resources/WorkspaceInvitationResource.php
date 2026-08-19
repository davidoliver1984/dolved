<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\WorkspaceInvitationStatus;
use App\Enums\WorkspaceRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actorRole = $request->attributes->get('workspace_role')
            ?? $this->workspace->memberships()->where('user_id', $request->user()?->id)->value('role');
        $effectiveStatus = $this->status === WorkspaceInvitationStatus::Pending && $this->expires_at->isPast()
            ? WorkspaceInvitationStatus::Expired
            : $this->status;

        return [
            'public_id' => $this->public_id,
            'invited_email' => $this->invited_email,
            'intended_role' => $this->intended_role->value,
            'status' => $effectiveStatus->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'capabilities' => [
                'revoke' => $effectiveStatus === WorkspaceInvitationStatus::Pending
                    && ($actorRole === WorkspaceRole::Owner->value
                        || ($actorRole === WorkspaceRole::Admin->value && $this->intended_role === WorkspaceRole::Member)),
            ],
        ];
    }
}
