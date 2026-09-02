<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Enums\DocumentGovernanceStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\User;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Documents\DocumentGovernanceAuthorizer;
use App\Support\Documents\LockDocumentFamilyLineage;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Illuminate\Support\Facades\DB;

final readonly class ApproveDocumentVersion
{
    public function __construct(
        private DocumentGovernanceAuthorizer $authorizer,
        private DocumentAuthorityTimeline $timeline,
        private LockDocumentFamilyLineage $lockLineage,
        private RecordDocumentGovernanceAudit $audit,
        private RecordDocumentGovernanceEvent $events,
    ) {}

    public function handle(Document $document, User $actor): Document
    {
        return DB::transaction(function () use ($document, $actor): Document {
            [, $locked, $versions] = $this->lockLineage->handle($document);
            $this->authorizer->ordinary($actor, $locked);

            if ($locked->governance_status !== DocumentGovernanceStatus::Draft) {
                throw new DocumentGovernanceException('Only a draft document version can be approved.');
            }

            $before = $locked->only(['governance_status', 'effective_from', 'approved_at', 'withdrawn_at']);
            $locked->governance_status = DocumentGovernanceStatus::Approved;
            $locked->approved_at = now();
            $locked->save();
            $this->timeline->assertConsistent($versions);
            $this->audit->record($locked, $actor, 'approved', $before, $locked->only(array_keys($before)));
            $this->events->record(
                $locked->workspace()->firstOrFail(),
                DocumentGovernanceEventKey::GovernanceVersionApproved,
                $locked->public_id,
                $locked->public_id,
                [
                    'initiating_user_public_id' => $locked->createdBy()->value('public_id'),
                    'approver_user_public_id' => $actor->public_id,
                    'target_kind' => 'document',
                    'target_public_id' => $locked->public_id,
                    'target_display_label' => mb_substr($locked->source_filename, 0, 255),
                ],
            );

            return $locked->refresh();
        });
    }
}
