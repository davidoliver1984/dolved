<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Enums\WorkspaceRole;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceEvent;
use App\Models\User;
use App\Models\WorkspaceMembership;

final class ResolveDocumentGovernanceRecipients
{
    /** @return list<array{user_id: int, user_public_id: string, membership_id: int}> */
    public function handle(DocumentGovernanceEvent $event): array
    {
        $payload = $event->payload;
        $publicIds = match ($event->event_key) {
            DocumentGovernanceEventKey::GovernanceReviewDueSoon,
            DocumentGovernanceEventKey::GovernanceReviewOverdue,
            DocumentGovernanceEventKey::GovernanceAuthorityBlocked => [
                ...$this->ownerPublicIds($event),
                ...$this->administratorPublicIds($event),
            ],
            DocumentGovernanceEventKey::GovernanceOwnershipReassignmentRequired => $this->administratorPublicIds($event),
            DocumentGovernanceEventKey::DeletionOperationStuckOrFailed => [
                ...array_values(array_filter([(string) ($payload['initiating_user_public_id'] ?? '')])),
                ...$this->administratorPublicIds($event),
            ],
            DocumentGovernanceEventKey::ImportBatchCompletedWithExceptions => [
                ...array_values(array_filter([(string) ($payload['initiating_user_public_id'] ?? '')])),
                ...(($payload['approval_required'] ?? false) === true ? $this->administratorPublicIds($event) : []),
            ],
            DocumentGovernanceEventKey::ImportItemProcessingFailed,
            DocumentGovernanceEventKey::ImportItemRequiresUserAction,
            DocumentGovernanceEventKey::ImportItemMatchAmbiguous,
            DocumentGovernanceEventKey::GovernanceAuthorityApproaching,
            DocumentGovernanceEventKey::GovernanceAuthorityAttained => [],
            default => array_values(array_filter([(string) ($payload['initiating_user_public_id'] ?? '')])),
        };

        if ($event->event_key === DocumentGovernanceEventKey::GovernanceVersionApproved) {
            $original = (string) ($payload['initiating_user_public_id'] ?? '');
            $approver = (string) ($payload['approver_user_public_id'] ?? '');
            $publicIds = $original !== '' && $original !== $approver ? [$original] : [];
        }

        $publicIds = array_values(array_unique($publicIds));
        if ($publicIds === []) {
            return [];
        }

        return WorkspaceMembership::query()
            ->join('users', 'users.id', '=', 'workspace_memberships.user_id')
            ->where('workspace_memberships.workspace_id', $event->workspace_id)
            ->whereIn('users.public_id', $publicIds)
            ->whereNull('users.disabled_at')
            ->orderBy('users.public_id')
            ->get([
                'users.id as user_id',
                'users.public_id as user_public_id',
                'workspace_memberships.id as membership_id',
            ])->map(fn ($row): array => [
                'user_id' => (int) $row->user_id,
                'user_public_id' => (string) $row->user_public_id,
                'membership_id' => (int) $row->membership_id,
            ])->all();
    }

    /** @return list<string> */
    private function ownerPublicIds(DocumentGovernanceEvent $event): array
    {
        $family = DocumentFamily::query()
            ->where('workspace_id', $event->workspace_id)
            ->where('public_id', (string) ($event->payload['document_family_public_id'] ?? ''))
            ->first();
        if (! $family?->owner_user_id) {
            return [];
        }

        return User::query()->whereKey($family->owner_user_id)->pluck('public_id')->all();
    }

    /** @return list<string> */
    private function administratorPublicIds(DocumentGovernanceEvent $event): array
    {
        return WorkspaceMembership::query()
            ->join('users', 'users.id', '=', 'workspace_memberships.user_id')
            ->where('workspace_memberships.workspace_id', $event->workspace_id)
            ->whereIn('workspace_memberships.role', [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])
            ->whereNull('users.disabled_at')
            ->orderBy('users.public_id')
            ->pluck('users.public_id')->all();
    }
}
