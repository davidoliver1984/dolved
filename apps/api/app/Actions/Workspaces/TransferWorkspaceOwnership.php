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

class TransferWorkspaceOwnership
{
    public function __construct(
        private readonly WorkspaceAdministrationCommandGuard $commands,
        private readonly RecordWorkspaceAdministrationAudit $audit,
    ) {}

    /** @return array{former_owner: WorkspaceMembership, owner: WorkspaceMembership} */
    public function handle(Workspace $workspace, User $actor, string $targetPublicId, string $key, string $correlationId): array
    {
        return DB::transaction(function () use ($workspace, $actor, $targetPublicId, $key, $correlationId): array {
            [$command, $replayed] = $this->commands->begin($workspace, $actor, $key, 'ownership.transfer', ['target_membership' => $targetPublicId]);
            $memberships = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)
                ->where(function ($query) use ($actor, $targetPublicId): void {
                    $query->where('user_id', $actor->id)->orWhere('public_id', $targetPublicId);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $actorMembership = $memberships->firstWhere('user_id', $actor->id);
            $target = $memberships->firstWhere('public_id', $targetPublicId);
            if (! $actorMembership instanceof WorkspaceMembership || ! $target instanceof WorkspaceMembership) {
                throw WorkspaceAdministrationException::conflict('ownership_transfer_stale', 'The ownership transfer target is no longer available.');
            }
            if ($replayed) {
                return [
                    'former_owner' => $actorMembership,
                    'owner' => $target,
                ];
            }
            if ($actorMembership->role !== WorkspaceRole::Owner || $target->user_id === $actor->id || $target->role === WorkspaceRole::Owner) {
                throw WorkspaceAdministrationException::conflict('ownership_transfer_forbidden', 'Only the current owner may transfer ownership to another active member.');
            }

            $targetBefore = $target->role;
            // The owner partial unique index is non-deferrable: demote first, then promote.
            $actorMembership->forceFill(['role' => WorkspaceRole::Admin])->save();
            $target->forceFill(['role' => WorkspaceRole::Owner])->save();
            $this->commands->complete($command, [
                'former_owner_membership' => $actorMembership->public_id,
                'owner_membership' => $target->public_id,
            ]);
            $this->audit->record(
                $workspace,
                $actor,
                'ownership_transferred',
                'membership',
                $target->public_id,
                ['actor_role' => WorkspaceRole::Owner->value, 'target_role' => $targetBefore->value],
                ['actor_role' => WorkspaceRole::Admin->value, 'target_role' => WorkspaceRole::Owner->value],
                $correlationId,
                ['former_owner_membership' => $actorMembership->public_id],
            );

            return ['former_owner' => $actorMembership, 'owner' => $target];
        });
    }
}
