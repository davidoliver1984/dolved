<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Jobs\ReconcileOwnershipEligibilityAfterMembershipChange;
use App\Models\OwnershipEligibilityReconciliation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAdministrationAuditEvent;
use Illuminate\Support\Str;

final class RecordOwnershipEligibilityReconciliation
{
    public function record(
        Workspace $workspace,
        User $affectedUser,
        string $membershipPublicId,
        WorkspaceAdministrationAuditEvent $cause,
    ): OwnershipEligibilityReconciliation {
        return $this->recordForCause($workspace, $affectedUser, $membershipPublicId, $cause->event_id);
    }

    public function recordForCause(
        Workspace $workspace,
        User $affectedUser,
        ?string $membershipPublicId,
        string $causeIdentity,
    ): OwnershipEligibilityReconciliation {
        OwnershipEligibilityReconciliation::query()->insertOrIgnore([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'affected_user_public_id' => $affectedUser->public_id,
            'membership_public_id' => $membershipPublicId,
            'eligibility_loss_cause_identity' => $causeIdentity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $record = OwnershipEligibilityReconciliation::query()
            ->where('workspace_id', $workspace->id)
            ->where('eligibility_loss_cause_identity', $causeIdentity)
            ->firstOrFail();
        ReconcileOwnershipEligibilityAfterMembershipChange::dispatch($record->id);

        return $record;
    }
}
