<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\User;
use App\Models\UserDisablementReconciliationSource;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\Documents\RecordOwnershipEligibilityReconciliation;
use Illuminate\Support\Facades\DB;

final readonly class FanOutUserDisablement
{
    public function __construct(private RecordOwnershipEligibilityReconciliation $reconciliations) {}

    public function handle(UserDisablementReconciliationSource $source, int $limit = 100): void
    {
        DB::transaction(function () use ($source, $limit): void {
            $work = UserDisablementReconciliationSource::query()->lockForUpdate()->findOrFail($source->id);
            if ($work->completed_at !== null) {
                return;
            }

            $user = User::query()->findOrFail($work->user_id);
            $memberships = WorkspaceMembership::query()
                ->where('user_id', $work->user_id)
                ->where('id', '>', $work->cursor_membership_id ?? 0)
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($memberships as $membership) {
                $this->reconciliations->recordForCause(
                    Workspace::query()->findOrFail($membership->workspace_id),
                    $user,
                    $membership->public_id,
                    $work->public_id,
                );
                $work->cursor_membership_id = $membership->id;
            }

            if ($memberships->count() < $limit) {
                $work->completed_at = now();
            }
            $work->save();
        }, 3);
    }
}
