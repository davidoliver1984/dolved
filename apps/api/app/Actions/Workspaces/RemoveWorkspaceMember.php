<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Exceptions\WorkspaceAdministrationException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use App\Support\Workspaces\WorkspaceAdministrationCommandGuard;
use Illuminate\Support\Facades\DB;

class RemoveWorkspaceMember
{
    public function __construct(
        private readonly WorkspaceAdministrationCommandGuard $commands,
        private readonly RecordWorkspaceAdministrationAudit $audit,
    ) {}

    /** @return array{removed: bool, membership_public_id: string} */
    public function handle(Workspace $workspace, User $actor, string $membershipPublicId, string $key, string $correlationId): array
    {
        return DB::transaction(function () use ($workspace, $actor, $membershipPublicId, $key, $correlationId): array {
            [$command, $replayed] = $this->commands->begin($workspace, $actor, $key, 'membership.remove', ['membership' => $membershipPublicId]);
            if ($replayed) {
                return ['removed' => true, 'membership_public_id' => $membershipPublicId];
            }
            $actorMembership = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)->where('user_id', $actor->id)
                ->lockForUpdate()->firstOrFail();
            $target = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)->where('public_id', $membershipPublicId)
                ->with('user')->lockForUpdate()->firstOrFail();
            if ($target->user_id === $actor->id) {
                throw WorkspaceAdministrationException::conflict('use_leave_workspace', 'Use the leave-workspace action to remove your own membership.');
            }
            $allowed = match ($actorMembership->role) {
                WorkspaceRole::Owner => $target->role !== WorkspaceRole::Owner,
                WorkspaceRole::Admin => $target->role === WorkspaceRole::Member,
                WorkspaceRole::Member => false,
            };
            if (! $allowed) {
                throw WorkspaceAdministrationException::conflict('member_removal_forbidden', 'You cannot remove this workspace member.');
            }
            $before = ['role' => $target->role->value];
            $targetPublicId = $target->public_id;
            $target->delete();
            $this->commands->complete($command, ['membership_public_id' => $targetPublicId, 'removed' => true]);
            $this->audit->record($workspace, $actor, 'member_removed', 'membership', $targetPublicId, $before, ['status' => 'removed'], $correlationId);

            return ['removed' => true, 'membership_public_id' => $targetPublicId];
        });
    }
}
