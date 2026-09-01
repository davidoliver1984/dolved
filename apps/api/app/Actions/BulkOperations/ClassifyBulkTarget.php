<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkEligibilityStatus;
use App\Enums\BulkExclusionReason;
use App\Enums\BulkOperationType;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFamily;
use App\Models\ImportItem;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

final class ClassifyBulkTarget
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{eligibility: BulkEligibilityStatus, reason: ?BulkExclusionReason, snapshot: array<string, mixed>}
     */
    public function handle(BulkOperationType $type, Document|DocumentFamily|ImportItem $target, Workspace $workspace, array $payload): array
    {
        return match ($type) {
            BulkOperationType::Approval => $this->approval($target),
            BulkOperationType::Promotion => $this->promotion($target),
            BulkOperationType::ApplicabilityChange => $this->applicability($target, $workspace, $payload),
            BulkOperationType::OwnerAssignment => $this->owner($target, $workspace, $payload),
            BulkOperationType::CategoryAssignment => $this->category($target, $workspace, $payload),
            BulkOperationType::TagChange => $this->tags($target, $workspace, $payload),
            BulkOperationType::ReviewDateAssignment => $this->reviewDate($target, $payload),
        };
    }

    private function approval(Document $document): array
    {
        $snapshot = ['status' => $document->status->value, 'governance_status' => $document->governance_status->value, 'effective_from' => $document->effective_from?->toISOString()];
        $reason = match (true) {
            $document->status !== DocumentStatus::Indexed => BulkExclusionReason::NotIndexed,
            $document->governance_status === DocumentGovernanceStatus::Withdrawn => BulkExclusionReason::Withdrawn,
            $document->governance_status !== DocumentGovernanceStatus::Draft => BulkExclusionReason::AlreadyApprovedOrCurrent,
            default => null,
        };

        return $this->result($snapshot, $reason);
    }

    private function promotion(ImportItem $item): array
    {
        $snapshot = [
            'preflight_status' => $item->preflight_status->value,
            'match_status' => $item->match_status->value,
            'decision_snapshot_id' => $item->current_decision_snapshot_id,
            'source_checksum_sha256' => $item->source_checksum_sha256,
        ];
        $reason = match (true) {
            $item->preflight_status !== ImportPreflightStatus::Verified => BulkExclusionReason::PreflightNotVerified,
            $item->match_status !== ImportMatchStatus::Resolved => BulkExclusionReason::MatchUnresolved,
            $item->current_decision_snapshot_id === null => BulkExclusionReason::ReadinessCriteriaIncomplete,
            default => null,
        };

        return $this->result($snapshot, $reason);
    }

    private function applicability(DocumentFamily $family, Workspace $workspace, array $payload): array
    {
        $current = $this->currentDocument($family);
        $requested = collect($payload['location_public_ids'] ?? [])->filter('is_string')->unique()->sort()->values();
        $valid = OrganisationalLocation::query()->where('workspace_id', $workspace->id)
            ->whereIn('public_id', $requested)->pluck('public_id')->sort()->values();
        $currentLocations = $current?->applicabilitySnapshot?->locations()->pluck('public_id')->sort()->values() ?? collect();
        $snapshot = ['current_document_public_id' => $current?->public_id, 'location_public_ids' => $currentLocations->all()];
        $reason = match (true) {
            $current === null => BulkExclusionReason::NoAuthoritativePredecessor,
            $valid->count() !== $requested->count() => BulkExclusionReason::InvalidOrRetiredLocation,
            $requested->all() === $currentLocations->all() => BulkExclusionReason::NoOpUnchangedApplicability,
            default => null,
        };

        return $this->result($snapshot, $reason);
    }

    private function owner(DocumentFamily $family, Workspace $workspace, array $payload): array
    {
        $requested = User::query()->where('public_id', $payload['owner_user_public_id'] ?? '')
            ->whereNull('disabled_at')->whereHas('workspaceMemberships', fn ($query) => $query->where('workspace_id', $workspace->id))->first();
        $snapshot = ['owner_user_id' => $family->owner_user_id];
        $reason = match (true) {
            $requested === null => BulkExclusionReason::RequestedOwnerNotActiveMember,
            (int) $family->owner_user_id === (int) $requested->id => BulkExclusionReason::CurrentOwnerAlreadyMatches,
            default => null,
        };

        return $this->result($snapshot, $reason);
    }

    private function category(DocumentFamily $family, Workspace $workspace, array $payload): array
    {
        $publicId = $payload['category_public_id'] ?? null;
        $category = $publicId === null ? null : DocumentCategory::query()->where('workspace_id', $workspace->id)->where('public_id', $publicId)->first();
        $snapshot = ['category_id' => $family->category_id];
        $reason = match (true) {
            $publicId !== null && ($category === null || $category->status !== DocumentCategoryStatus::Active) => BulkExclusionReason::CategoryArchivedOrDeleted,
            ($category?->id ?? null) === $family->category_id => BulkExclusionReason::AlreadyAssigned,
            default => null,
        };

        return $this->result($snapshot, $reason);
    }

    private function tags(DocumentFamily $family, Workspace $workspace, array $payload): array
    {
        $current = $family->tags()->pluck('document_tags.public_id')->sort()->values();
        $requested = collect($payload['tag_public_ids'] ?? [])->filter('is_string')->unique()->sort()->values();
        $existing = $workspace->documentTags()->whereIn('public_id', $requested)->pluck('public_id')->sort()->values();
        $mode = (string) ($payload['mode'] ?? 'replace');
        $result = match ($mode) {
            'add' => $current->merge($existing)->unique()->sort()->values(),
            'remove' => $current->diff($existing)->sort()->values(),
            default => $existing,
        };
        $snapshot = ['tag_public_ids' => $current->all()];
        $reason = match (true) {
            $requested->count() !== $existing->count(), $result->count() > 20 => BulkExclusionReason::TagLimitExceeded,
            $result->all() === $current->all() => BulkExclusionReason::AddRemoveReplaceNoOp,
            default => null,
        };

        return $this->result($snapshot, $reason);
    }

    private function reviewDate(DocumentFamily $family, array $payload): array
    {
        $requested = $payload['review_due_date'] ?? null;
        try {
            $date = $requested === null ? null : CarbonImmutable::createFromFormat('!Y-m-d', (string) $requested);
        } catch (\Throwable) {
            $date = false;
        }
        $current = $family->review_due_date?->format('Y-m-d');
        $snapshot = ['review_due_date' => $current];
        $reason = match (true) {
            $date === false => BulkExclusionReason::InvalidDate,
            ($date instanceof CarbonImmutable ? $date->format('Y-m-d') : null) === $current => BulkExclusionReason::SameExistingDate,
            default => null,
        };

        return $this->result($snapshot, $reason);
    }

    private function currentDocument(DocumentFamily $family): ?Document
    {
        $now = now();

        return $family->documents()->with('applicabilitySnapshot.locations')
            ->where('status', DocumentStatus::Indexed->value)
            ->where('governance_status', DocumentGovernanceStatus::Approved->value)
            ->whereNotNull('approved_at')->where('approved_at', '<=', $now)
            ->where('effective_from', '<=', $now)
            ->where(fn ($query) => $query->whereNull('withdrawn_at')->orWhere('withdrawn_at', '>', $now))
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
    }

    private function result(array $snapshot, ?BulkExclusionReason $reason): array
    {
        return [
            'eligibility' => $reason === null ? BulkEligibilityStatus::Eligible : BulkEligibilityStatus::Excluded,
            'reason' => $reason,
            'snapshot' => $snapshot,
        ];
    }
}
