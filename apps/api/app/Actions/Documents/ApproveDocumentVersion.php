<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\User;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Documents\DocumentGovernanceAuthorizer;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Illuminate\Support\Facades\DB;

final readonly class ApproveDocumentVersion
{
    public function __construct(
        private DocumentGovernanceAuthorizer $authorizer,
        private DocumentAuthorityTimeline $timeline,
        private RecordDocumentGovernanceAudit $audit,
    ) {}

    public function handle(Document $document, User $actor): Document
    {
        return DB::transaction(function () use ($document, $actor): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $this->authorizer->ordinary($actor, $locked);

            if ($locked->governance_status !== DocumentGovernanceStatus::Draft) {
                throw new DocumentGovernanceException('Only a draft document version can be approved.');
            }

            $before = $locked->only(['governance_status', 'effective_from', 'approved_at', 'withdrawn_at']);
            $locked->governance_status = DocumentGovernanceStatus::Approved;
            $locked->approved_at = now();
            $locked->save();
            $this->timeline->assertConsistent(
                Document::query()->where('document_family_id', $locked->document_family_id)->lockForUpdate()->get(),
            );
            $this->audit->record($locked, $actor, 'approved', $before, $locked->only(array_keys($before)));

            return $locked->refresh();
        });
    }
}
