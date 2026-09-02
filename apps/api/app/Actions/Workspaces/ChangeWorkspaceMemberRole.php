<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Exceptions\WorkspaceAdministrationException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\Documents\RecordOwnershipEligibilityReconciliation;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use App\Support\Workspaces\WorkspaceAdministrationCommandGuard;
use Illuminate\Support\Facades\DB;

class ChangeWorkspaceMemberRole
{
    public function __construct(
        private readonly WorkspaceAdministrationCommandGuard $commands,
        private readonly RecordWorkspaceAdministrationAudit $audit,
        private readonly RecordOwnershipEligibilityReconciliation $ownershipEligibility,
    ) {}

    public function handle(Workspace $workspace, User $actor, string $membershipPublicId, WorkspaceRole $role, string $key, string $correlationId): WorkspaceMembership
    {
        return DB::transaction(function () use ($workspace, $actor, $membershipPublicId, $role, $key, $correlationId): WorkspaceMembership {
            [$command, $replayed] = $this->commands->begin($workspace, $actor, $key, 'membership.role.change', [
                'membership' => $membershipPublicId,
                'role' => $role->value,
            ]);
            $actorMembership = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)->where('user_id', $actor->id)
                ->lockForUpdate()->firstOrFail();
            $target = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)->where('public_id', $membershipPublicId)
                ->with('user')->lockForUpdate()->firstOrFail();
            if ($replayed) {
                return $target;
            }
            if ($actorMembership->role !== WorkspaceRole::Owner
                || $target->role === WorkspaceRole::Owner
                || ! in_array($role, [WorkspaceRole::Admin, WorkspaceRole::Member], true)) {
                throw WorkspaceAdministrationException::conflict('role_change_forbidden', 'Only the owner may promote or demote an administrator.');
            }
            $before = $target->role;
            if ($before !== $role) {
                $target->forceFill(['role' => $role])->save();
                $audit = $this->audit->record($workspace, $actor, 'member_role_changed', 'membership', $target->public_id, ['role' => $before->value], ['role' => $role->value], $correlationId);
                if ($role === WorkspaceRole::Member) {
                    $this->ownershipEligibility->record($workspace, $target->user, $target->public_id, $audit);
                }
            }
            $this->commands->complete($command, ['membership_public_id' => $target->public_id, 'role' => $target->role->value]);

            return $target;
        });
    }
}
