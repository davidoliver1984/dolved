<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\User;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Documents\DocumentGovernanceAuthorizer;
use App\Support\Documents\LockDocumentFamilyLineage;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class CorrectDocumentGovernanceTimestamps
{
    public function __construct(
        private DocumentGovernanceAuthorizer $authorizer,
        private DocumentAuthorityTimeline $timeline,
        private LockDocumentFamilyLineage $lockLineage,
        private RecordDocumentGovernanceAudit $audit,
    ) {}

    public function handle(
        Document $document,
        User $actor,
        CarbonInterface $approvedAt,
        ?CarbonInterface $withdrawnAt,
        string $reason,
    ): Document {
        return DB::transaction(function () use ($document, $actor, $approvedAt, $withdrawnAt, $reason): Document {
            [, $locked, $versions] = $this->lockLineage->handle($document);
            $this->authorizer->historicalCorrection($actor, $locked);
            $reason = trim($reason);

            if ($reason === '') {
                throw new DocumentGovernanceException('A historical correction requires a reason.');
            }
            if ($locked->governance_status === DocumentGovernanceStatus::Draft) {
                throw new DocumentGovernanceException('Draft versions have no governance timestamps to correct.');
            }
            if ($approvedAt->isFuture() || $withdrawnAt?->isFuture()) {
                throw new DocumentGovernanceException('Historical corrections cannot introduce future event timestamps.');
            }
            if ($locked->governance_status === DocumentGovernanceStatus::Withdrawn && $withdrawnAt === null) {
                throw new DocumentGovernanceException('A withdrawn version requires its withdrawal timestamp.');
            }
            if ($locked->governance_status === DocumentGovernanceStatus::Approved && $withdrawnAt !== null) {
                throw new DocumentGovernanceException('An approved version cannot have a withdrawal timestamp.');
            }
            if ($withdrawnAt !== null && $withdrawnAt->lt($approvedAt)) {
                throw new DocumentGovernanceException('Withdrawal cannot precede approval.');
            }

            $before = $locked->only(['governance_status', 'effective_from', 'approved_at', 'withdrawn_at']);
            $locked->approved_at = $approvedAt;
            $locked->withdrawn_at = $withdrawnAt;
            $locked->save();
            $this->timeline->assertConsistent($versions);
            $this->audit->record($locked, $actor, 'historical_timestamps_corrected', $before, $locked->only(array_keys($before)), $reason);

            return $locked->refresh();
        });
    }
}
