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
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class RescheduleDocumentVersion
{
    public function __construct(
        private DocumentGovernanceAuthorizer $authorizer,
        private DocumentAuthorityTimeline $timeline,
        private RecordDocumentGovernanceAudit $audit,
    ) {}

    public function handle(Document $document, User $actor, CarbonInterface $effectiveFrom): Document
    {
        return DB::transaction(function () use ($document, $actor, $effectiveFrom): Document {
            $locked = Document::query()->with('predecessor')->lockForUpdate()->findOrFail($document->id);
            $this->authorizer->ordinary($actor, $locked);

            if ($locked->governance_status !== DocumentGovernanceStatus::Approved) {
                throw new DocumentGovernanceException('Only an approved version may be rescheduled.');
            }
            if ($this->timeline->authorityStart($locked)?->lte(now()) !== false) {
                throw new DocumentGovernanceException('A document version cannot be rescheduled after attaining authority.');
            }
            if ($locked->predecessor !== null && $effectiveFrom->lte($locked->predecessor->effective_from)) {
                throw new DocumentGovernanceException('A successor must remain later than its predecessor.');
            }

            $before = $locked->only(['governance_status', 'effective_from', 'approved_at', 'withdrawn_at']);
            $locked->effective_from = $effectiveFrom;
            $locked->save();
            $this->timeline->assertConsistent(
                Document::query()->where('document_family_id', $locked->document_family_id)->lockForUpdate()->get(),
            );
            $this->audit->record($locked, $actor, 'rescheduled', $before, $locked->only(array_keys($before)));

            return $locked->refresh();
        });
    }
}
