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
use Illuminate\Support\Str;

class IssueWorkspaceInvitation
{
    public function __construct(
        private readonly WorkspaceAdministrationCommandGuard $commands,
        private readonly RecordWorkspaceAdministrationAudit $audit,
    ) {}

    /** @return array{invitation: ?WorkspaceInvitation, token: ?string, replayed: bool, already_member: bool} */
    public function handle(
        Workspace $workspace,
        User $actor,
        string $email,
        WorkspaceRole $role,
        string $idempotencyKey,
        string $correlationId,
    ): array {
        $normalizedEmail = mb_strtolower(trim($email));

        return DB::transaction(function () use ($workspace, $actor, $normalizedEmail, $role, $idempotencyKey, $correlationId): array {
            [$command, $replayed] = $this->commands->begin(
                $workspace,
                $actor,
                $idempotencyKey,
                'invitation.issue',
                ['email' => $normalizedEmail, 'role' => $role->value],
            );
            if ($replayed) {
                $invitation = isset($command->result['invitation_public_id'])
                    ? WorkspaceInvitation::query()->where('public_id', $command->result['invitation_public_id'])->first()
                    : null;

                return [
                    'invitation' => $invitation,
                    'token' => null,
                    'replayed' => true,
                    'already_member' => (bool) ($command->result['already_member'] ?? false),
                ];
            }

            $actorMembership = WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($actorMembership->role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true)
                || $role === WorkspaceRole::Owner
                || ($actorMembership->role === WorkspaceRole::Admin && $role !== WorkspaceRole::Member)) {
                throw WorkspaceAdministrationException::conflict('invitation_role_forbidden', 'You cannot issue an invitation for that role.');
            }

            $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
            $existingMembership = $existingUser === null ? null : WorkspaceMembership::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $existingUser->id)
                ->lockForUpdate()
                ->first();
            if ($existingMembership !== null) {
                if ($existingMembership->role !== $role) {
                    throw WorkspaceAdministrationException::conflict('membership_role_conflict', 'That account already belongs to this workspace with a different role.');
                }
                $this->commands->complete($command, ['already_member' => true]);

                return ['invitation' => null, 'token' => null, 'replayed' => false, 'already_member' => true];
            }

            $pending = WorkspaceInvitation::query()
                ->where('workspace_id', $workspace->id)
                ->where('invited_email', $normalizedEmail)
                ->where('status', WorkspaceInvitationStatus::Pending->value)
                ->lockForUpdate()
                ->first();
            if ($pending !== null) {
                $expired = $pending->expires_at->isPast();
                $pending->forceFill([
                    'status' => $expired ? WorkspaceInvitationStatus::Expired : WorkspaceInvitationStatus::Revoked,
                    'revoked_at' => $expired ? null : now(),
                ])->save();
                $this->audit->record(
                    $workspace,
                    $actor,
                    $expired ? 'invitation_expired' : 'invitation_revoked',
                    'invitation',
                    $pending->public_id,
                    ['status' => WorkspaceInvitationStatus::Pending->value],
                    ['status' => $pending->status->value],
                    $correlationId,
                    ['reason' => 'reissued'],
                );
            }

            $token = bin2hex(random_bytes(32));
            $invitation = WorkspaceInvitation::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'invited_email' => $normalizedEmail,
                'intended_role' => $role,
                'invited_by_user_id' => $actor->id,
                'token_digest' => hash('sha256', $token),
                'status' => WorkspaceInvitationStatus::Pending,
                'expires_at' => now()->addDays(max(1, (int) config('workspace_administration.invitation_days'))),
            ]);
            $this->commands->complete($command, [
                'invitation_public_id' => $invitation->public_id,
                'already_member' => false,
            ]);
            $this->audit->record(
                $workspace,
                $actor,
                'invitation_issued',
                'invitation',
                $invitation->public_id,
                null,
                ['status' => $invitation->status->value, 'intended_role' => $role->value],
                $correlationId,
            );

            return ['invitation' => $invitation, 'token' => $token, 'replayed' => false, 'already_member' => false];
        });
    }
}
