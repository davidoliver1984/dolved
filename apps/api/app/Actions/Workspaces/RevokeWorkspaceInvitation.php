<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceInvitationStatus;
use App\Enums\WorkspaceRole;
use App\Exceptions\WorkspaceAdministrationException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use App\Support\Workspaces\WorkspaceAdministrationCommandGuard;
use Illuminate\Support\Facades\DB;

class RevokeWorkspaceInvitation
{
    public function __construct(
        private readonly WorkspaceAdministrationCommandGuard $commands,
        private readonly RecordWorkspaceAdministrationAudit $audit,
    ) {}

    public function handle(Workspace $workspace, User $actor, string $invitationPublicId, string $key, string $correlationId): WorkspaceInvitation
    {
        return DB::transaction(function () use ($workspace, $actor, $invitationPublicId, $key, $correlationId): WorkspaceInvitation {
            [$command, $replayed] = $this->commands->begin(
                $workspace, $actor, $key, 'invitation.revoke', ['invitation' => $invitationPublicId],
            );
            $invitation = WorkspaceInvitation::query()
                ->where('workspace_id', $workspace->id)
                ->where('public_id', $invitationPublicId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($replayed) {
                return $invitation;
            }
            $actorRole = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)->where('user_id', $actor->id)
                ->lockForUpdate()->value('role');
            if ($actorRole === WorkspaceRole::Admin->value && $invitation->intended_role !== WorkspaceRole::Member) {
                throw WorkspaceAdministrationException::conflict('invitation_revoke_forbidden', 'An admin may revoke only ordinary-member invitations.');
            }
            if (! in_array($actorRole, [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value], true)) {
                throw WorkspaceAdministrationException::conflict('invitation_revoke_forbidden', 'You cannot revoke this invitation.');
            }
            if ($invitation->status === WorkspaceInvitationStatus::Pending) {
                $before = ['status' => $invitation->status->value];
                $invitation->forceFill(['status' => WorkspaceInvitationStatus::Revoked, 'revoked_at' => now()])->save();
                $this->audit->record($workspace, $actor, 'invitation_revoked', 'invitation', $invitation->public_id, $before, ['status' => $invitation->status->value], $correlationId);
            }
            $this->commands->complete($command, ['invitation_public_id' => $invitation->public_id, 'status' => $invitation->status->value]);

            return $invitation;
        });
    }
}
