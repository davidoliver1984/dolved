<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Models\DocumentFamily;
use App\Models\OwnershipEligibilityReconciliation;
use App\Models\Workspace;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileOwnershipEligibility
{
    public function __construct(private RecordDocumentGovernanceEvent $events) {}

    public function handle(OwnershipEligibilityReconciliation $source, int $limit = 100): void
    {
        DB::transaction(function () use ($source, $limit): void {
            $work = OwnershipEligibilityReconciliation::query()->lockForUpdate()->findOrFail($source->id);
            if ($work->completed_at !== null) {
                return;
            }
            $families = DocumentFamily::query()
                ->join('users', 'users.id', '=', 'document_families.owner_user_id')
                ->where('document_families.workspace_id', $work->workspace_id)
                ->where('users.public_id', $work->affected_user_public_id)
                ->where('document_families.id', '>', $work->cursor_family_id ?? 0)
                ->orderBy('document_families.id')->limit($limit)
                ->get('document_families.*');
            $workspace = Workspace::query()->findOrFail($work->workspace_id);
            foreach ($families as $family) {
                $occurrence = implode(':', [
                    $family->public_id,
                    $work->affected_user_public_id,
                    $work->eligibility_loss_cause_identity,
                ]);
                $this->events->record(
                    $workspace,
                    DocumentGovernanceEventKey::GovernanceOwnershipReassignmentRequired,
                    $family->public_id,
                    $occurrence,
                    [
                        'document_family_public_id' => $family->public_id,
                        'affected_owner_user_public_id' => $work->affected_user_public_id,
                        'eligibility_loss_cause_identity' => $work->eligibility_loss_cause_identity,
                        'target_kind' => 'family',
                        'target_public_id' => $family->public_id,
                        'target_display_label' => mb_substr($family->name, 0, 255),
                    ],
                );
                $work->cursor_family_id = $family->id;
            }
            if ($families->count() < $limit) {
                $work->completed_at = now();
            }
            $work->save();
        }, 3);
    }
}
