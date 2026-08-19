<?php

declare(strict_types=1);

namespace App\Support\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAdministrationAuditEvent;
use Illuminate\Support\Str;

class RecordWorkspaceAdministrationAudit
{
    public function record(
        Workspace $workspace,
        ?User $actor,
        string $action,
        string $targetType,
        ?string $targetPublicId,
        ?array $before,
        ?array $after,
        string $correlationId,
        array $context = [],
    ): WorkspaceAdministrationAuditEvent {
        return WorkspaceAdministrationAuditEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_public_id' => $targetPublicId,
            'before' => $before,
            'after' => $after,
            'context' => $context,
            'correlation_id' => $correlationId,
            'occurred_at' => now(),
        ]);
    }
}
