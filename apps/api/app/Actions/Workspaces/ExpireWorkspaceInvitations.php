<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceInvitationStatus;
use App\Models\WorkspaceInvitation;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpireWorkspaceInvitations
{
    public function __construct(private readonly RecordWorkspaceAdministrationAudit $audit) {}

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $invitations = WorkspaceInvitation::query()
                ->with('workspace')
                ->where('status', WorkspaceInvitationStatus::Pending)
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($invitations as $invitation) {
                $invitation->forceFill(['status' => WorkspaceInvitationStatus::Expired])->save();
                $this->audit->record(
                    $invitation->workspace,
                    null,
                    'invitation_expired',
                    'invitation',
                    $invitation->public_id,
                    ['status' => WorkspaceInvitationStatus::Pending->value],
                    ['status' => WorkspaceInvitationStatus::Expired->value],
                    (string) Str::uuid(),
                    ['reason' => 'scheduled_expiry'],
                );
            }

            return $invitations->count();
        });
    }
}
