<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceInvitationStatus;
use App\Exceptions\WorkspaceAdministrationException;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use Illuminate\Support\Facades\DB;

class AcceptWorkspaceInvitation
{
    public function __construct(private readonly RecordWorkspaceAdministrationAudit $audit) {}

    public function handle(User $user, string $token, string $correlationId): WorkspaceMembership
    {
        $result = DB::transaction(function () use ($user, $token, $correlationId): array {
            $invitation = WorkspaceInvitation::query()
                ->with('workspace')
                ->where('token_digest', hash('sha256', $token))
                ->lockForUpdate()
                ->first();
            if ($invitation === null
                || $user->email_verified_at === null
                || ! hash_equals($invitation->invited_email, mb_strtolower(trim($user->email)))) {
                return ['membership' => null];
            }
            if ($invitation->status === WorkspaceInvitationStatus::Accepted
                && $invitation->accepted_by_user_id === $user->id) {
                return ['membership' => WorkspaceMembership::query()
                    ->where('workspace_id', $invitation->workspace_id)->where('user_id', $user->id)->first()];
            }
            if ($invitation->status !== WorkspaceInvitationStatus::Pending) {
                return ['membership' => null];
            }
            if ($invitation->expires_at->isPast()) {
                $invitation->forceFill(['status' => WorkspaceInvitationStatus::Expired])->save();
                $this->audit->record($invitation->workspace, $user, 'invitation_expired', 'invitation', $invitation->public_id, ['status' => 'pending'], ['status' => 'expired'], $correlationId, ['reason' => 'acceptance_after_expiry']);

                return ['membership' => null];
            }
            $membership = WorkspaceMembership::query()
                ->where('workspace_id', $invitation->workspace_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if ($membership !== null && $membership->role !== $invitation->intended_role) {
                throw WorkspaceAdministrationException::conflict('membership_role_conflict', 'The invitation cannot alter an existing workspace role.');
            }
            $membership ??= WorkspaceMembership::query()->create([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->id,
                'role' => $invitation->intended_role,
                'joined_at' => now(),
            ]);
            $invitation->forceFill([
                'status' => WorkspaceInvitationStatus::Accepted,
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ])->save();
            $this->audit->record($invitation->workspace, $user, 'invitation_accepted', 'invitation', $invitation->public_id, ['status' => 'pending'], ['status' => 'accepted', 'role' => $membership->role->value], $correlationId);

            return ['membership' => $membership];
        });
        if (! $result['membership'] instanceof WorkspaceMembership) {
            throw WorkspaceAdministrationException::unavailable();
        }

        return $result['membership'];
    }
}
