<?php

declare(strict_types=1);

namespace App\Support\Workspaces;

use App\Exceptions\WorkspaceAdministrationException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAdministrationCommand;
use Illuminate\Support\Str;

class WorkspaceAdministrationCommandGuard
{
    /** @return array{WorkspaceAdministrationCommand, bool} */
    public function begin(
        Workspace $workspace,
        User $actor,
        string $key,
        string $type,
        array $request,
    ): array {
        ksort($request);
        $digest = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
        $existing = WorkspaceAdministrationCommand::query()
            ->where('workspace_id', $workspace->id)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();
        if ($existing !== null) {
            if ($existing->command_type !== $type || ! hash_equals($existing->request_digest, $digest)) {
                throw WorkspaceAdministrationException::conflict(
                    'idempotency_key_reused',
                    'The idempotency key was already used for a different administration command.',
                );
            }

            return [$existing, true];
        }

        return [WorkspaceAdministrationCommand::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'requested_by_user_id' => $actor->id,
            'idempotency_key' => $key,
            'command_type' => $type,
            'request_digest' => $digest,
        ]), false];
    }

    public function complete(WorkspaceAdministrationCommand $command, array $result): void
    {
        $command->forceFill(['result' => $result])->save();
    }
}
