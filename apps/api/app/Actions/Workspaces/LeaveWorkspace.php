<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Exceptions\WorkspaceAdministrationException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use Illuminate\Support\Facades\DB;

class LeaveWorkspace
{
    public function __construct(private readonly RecordWorkspaceAdministrationAudit $audit) {}

    public function handle(Workspace $workspace, User $actor, string $correlationId): void
    {
        DB::transaction(function () use ($workspace, $actor, $correlationId): void {
            $membership = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)->where('user_id', $actor->id)
                ->lockForUpdate()->firstOrFail();
            if ($membership->role === WorkspaceRole::Owner) {
                throw WorkspaceAdministrationException::conflict('owner_must_transfer', 'Transfer workspace ownership before leaving.');
            }
            $publicId = $membership->public_id;
            $role = $membership->role->value;
            $membership->delete();
            $this->audit->record($workspace, $actor, 'member_left', 'membership', $publicId, ['role' => $role], ['status' => 'removed'], $correlationId);
        });
    }
}
