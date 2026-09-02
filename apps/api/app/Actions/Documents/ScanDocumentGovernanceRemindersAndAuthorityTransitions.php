<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Enums\DocumentGovernanceStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\Workspace;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Carbon\CarbonImmutable;

final readonly class ScanDocumentGovernanceRemindersAndAuthorityTransitions
{
    public function __construct(private RecordDocumentGovernanceEvent $events) {}

    public function handle(): int
    {
        $today = CarbonImmutable::today('UTC');
        $lead = (int) config('documents.review_due_soon_lead_days', 14);
        $recorded = 0;

        DocumentFamily::query()->whereNotNull('review_due_date')->whereNull('tombstoned_at')
            ->whereDate('review_due_date', '<=', $today->addDays($lead))->orderBy('id')->chunkById(100, function ($families) use ($today, &$recorded): void {
                foreach ($families as $family) {
                    $overdue = $family->review_due_date->lessThan($today);
                    $kind = $overdue ? 'overdue' : 'due_soon';
                    $key = $overdue ? DocumentGovernanceEventKey::GovernanceReviewOverdue : DocumentGovernanceEventKey::GovernanceReviewDueSoon;
                    $this->events->record(
                        Workspace::query()->findOrFail($family->workspace_id),
                        $key,
                        'review:'.$family->public_id,
                        implode('|', [$family->public_id, $kind, $family->review_due_date->toDateString()]),
                        [
                            'document_family_public_id' => $family->public_id,
                            'target_kind' => 'document_family',
                            'target_public_id' => $family->public_id,
                            'target_display_label' => $family->name,
                            'review_due_date' => $family->review_due_date->toDateString(),
                        ],
                    );
                    $recorded++;
                }
            });

        Document::query()->where('governance_status', DocumentGovernanceStatus::Approved->value)
            ->whereNotNull('approved_at')->whereNotNull('effective_from')->orderBy('id')->chunkById(100, function ($documents) use ($today, $lead, &$recorded): void {
                foreach ($documents as $document) {
                    $authorityStart = $document->effective_from->greaterThan($document->approved_at)
                        ? $document->effective_from : $document->approved_at;
                    $successor = Document::query()->where('predecessor_document_id', $document->id)
                        ->where('governance_status', DocumentGovernanceStatus::Approved->value)
                        ->whereNotNull('approved_at')->whereNotNull('effective_from')->orderBy('id')->first();
                    if ($successor) {
                        $successorAuthorityStart = $successor->effective_from->greaterThan($successor->approved_at)
                            ? $successor->effective_from : $successor->approved_at;
                        if ($successorAuthorityStart->lessThanOrEqualTo($today->endOfDay())
                            && $successorAuthorityStart->lessThanOrEqualTo($authorityStart)) {
                            $family = DocumentFamily::query()->findOrFail($document->document_family_id);
                            $this->events->record(
                                Workspace::query()->findOrFail($document->workspace_id),
                                DocumentGovernanceEventKey::GovernanceAuthorityBlocked,
                                'authority:'.$document->public_id,
                                implode('|', [$document->public_id, 'authority_blocked', $successor->public_id]),
                                [
                                    'document_family_public_id' => $family->public_id,
                                    'target_kind' => 'document',
                                    'target_public_id' => $document->public_id,
                                    'target_display_label' => $document->source_filename,
                                    'blocking_successor_document_id' => $successor->public_id,
                                ],
                            );
                            $recorded++;

                            continue;
                        }
                    }
                    $key = null;
                    $kind = null;
                    if ($authorityStart->lessThanOrEqualTo($today->endOfDay())) {
                        $key = DocumentGovernanceEventKey::GovernanceAuthorityAttained;
                        $kind = 'authority_attained';
                    } elseif ($authorityStart->lessThanOrEqualTo($today->addDays($lead)->endOfDay())) {
                        $key = DocumentGovernanceEventKey::GovernanceAuthorityApproaching;
                        $kind = 'authority_approaching';
                    }
                    if (! $key) {
                        continue;
                    }
                    $family = DocumentFamily::query()->findOrFail($document->document_family_id);
                    $this->events->record(
                        Workspace::query()->findOrFail($document->workspace_id),
                        $key,
                        'authority:'.$document->public_id,
                        implode('|', [$document->public_id, $kind, $authorityStart->toIso8601String()]),
                        [
                            'document_family_public_id' => $family->public_id,
                            'target_kind' => 'document',
                            'target_public_id' => $document->public_id,
                            'target_display_label' => $document->source_filename,
                            'authority_start' => $authorityStart->toIso8601String(),
                        ],
                    );
                    $recorded++;
                }
            });

        return $recorded;
    }
}
