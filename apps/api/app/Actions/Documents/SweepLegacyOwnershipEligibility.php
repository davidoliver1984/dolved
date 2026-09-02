<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Models\DocumentFamily;
use App\Models\Workspace;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Illuminate\Database\Eloquent\Builder;

final readonly class SweepLegacyOwnershipEligibility
{
    public function __construct(private RecordDocumentGovernanceEvent $events) {}

    /** @return array{count: int, last_family_id: ?int} */
    public function handle(int $afterFamilyId = 0, int $limit = 100): array
    {
        $families = DocumentFamily::query()
            ->with(['owner', 'workspace'])
            ->where('id', '>', $afterFamilyId)
            ->where(function (Builder $query): void {
                $query->whereHas('owner', fn (Builder $owner): Builder => $owner->whereNotNull('disabled_at'))
                    ->orWhereDoesntHave('owner.workspaceMemberships', function (Builder $membership): void {
                        $membership->whereColumn('workspace_memberships.workspace_id', 'document_families.workspace_id');
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($families as $family) {
            $owner = $family->owner;
            $workspace = $family->workspace;
            if ($owner === null || ! $workspace instanceof Workspace) {
                continue;
            }
            $causeIdentity = implode(':', [
                $family->public_id,
                $family->owner_assignment_generation,
                $owner->public_id,
            ]);
            $this->events->record(
                $workspace,
                DocumentGovernanceEventKey::GovernanceOwnershipReassignmentRequired,
                $family->public_id,
                $causeIdentity,
                [
                    'document_family_public_id' => $family->public_id,
                    'affected_owner_user_public_id' => $owner->public_id,
                    'eligibility_loss_cause_identity' => $causeIdentity,
                    'target_kind' => 'family',
                    'target_public_id' => $family->public_id,
                    'target_display_label' => mb_substr($family->name, 0, 255),
                ],
            );
        }

        return [
            'count' => $families->count(),
            'last_family_id' => $families->last()?->id,
        ];
    }
}
