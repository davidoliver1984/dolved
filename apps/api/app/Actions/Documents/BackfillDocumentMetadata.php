<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\ChecksumUnavailableReason;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentGovernanceSystemActorCode;
use App\Enums\DocumentStatus;
use App\Exceptions\DocumentUploadException;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceAuditEvent;
use App\Services\Documents\DocumentObjectStorage;
use App\Support\Documents\DeriveDocumentFamilyTitle;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class BackfillDocumentMetadata
{
    public const CHECKSUM_ALGORITHM = 'sha256-stream-v1';

    public function __construct(
        private readonly DocumentObjectStorage $storage,
        private readonly RecordDocumentGovernanceAudit $audit,
    ) {}

    /**
     * @return array{
     *   owners: int,
     *   titles: int,
     *   audit_lineages: int,
     *   checksums_verified: int,
     *   checksums_unavailable: int,
     *   checksums_retryable: int,
     *   remaining: int
     * }
     */
    public function handle(int $batchSize = 100): array
    {
        $batchSize = max(1, min($batchSize, 1000));
        $summary = [
            'owners' => $this->backfillOwners($batchSize),
            'titles' => $this->backfillTitles($batchSize),
            'audit_lineages' => $this->recordAuditLineage($batchSize),
            'checksums_verified' => 0,
            'checksums_unavailable' => 0,
            'checksums_retryable' => 0,
            'remaining' => 0,
        ];

        foreach ($this->pendingDocuments($batchSize) as $document) {
            $outcome = $this->backfillChecksum($document);

            if ($outcome !== null) {
                $summary[$outcome]++;
            }
        }

        $this->finaliseOwnerConstraintWhenComplete();
        $summary['remaining'] = $this->remainingCount();

        return $summary;
    }

    private function backfillOwners(int $batchSize): int
    {
        $count = 0;
        $familyIds = DocumentFamily::query()
            ->whereNull('owner_user_id')
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        foreach ($familyIds as $familyId) {
            DB::transaction(function () use ($familyId, &$count): void {
                $family = DocumentFamily::query()->whereKey($familyId)->lockForUpdate()->firstOrFail();

                if ($family->owner_user_id !== null) {
                    return;
                }

                $root = Document::query()
                    ->where('document_family_id', $family->id)
                    ->whereNull('predecessor_document_id')
                    ->first();
                $ownerId = $root?->created_by_user_id;
                $actor = DocumentGovernanceSystemActorCode::OwnerBackfillLineageRoot;

                if ($ownerId === null) {
                    $ownerId = $family->workspace()->value('created_by_user_id');
                    $actor = DocumentGovernanceSystemActorCode::OwnerBackfillWorkspaceCreatorFallback;
                }

                if ($ownerId === null) {
                    return;
                }

                $family->owner_user_id = $ownerId;
                $family->save();
                $this->audit->recordSystemFamily(
                    $family,
                    $actor,
                    'document_family_owner_backfilled',
                    ['owner_user_id' => null],
                    ['owner_user_id' => $ownerId],
                );
                $count++;
            });
        }

        return $count;
    }

    private function backfillTitles(int $batchSize): int
    {
        $count = 0;
        $familyIds = $this->titleCandidates()
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        foreach ($familyIds as $familyId) {
            DB::transaction(function () use ($familyId, &$count): void {
                $family = DocumentFamily::query()->whereKey($familyId)->lockForUpdate()->firstOrFail();
                $root = Document::query()
                    ->where('document_family_id', $family->id)
                    ->whereNull('predecessor_document_id')
                    ->first();

                if ($root === null || $family->name !== $root->source_filename) {
                    return;
                }

                $before = $family->name;
                $family->name = DeriveDocumentFamilyTitle::fromSourceFilename($root->source_filename);
                $family->save();
                $this->audit->recordSystemFamily(
                    $family,
                    DocumentGovernanceSystemActorCode::TitleBackfill,
                    'document_family_title_reinterpreted',
                    ['name' => $before],
                    ['name' => $family->name],
                );
                $count++;
            });
        }

        return $count;
    }

    private function recordAuditLineage(int $batchSize): int
    {
        $count = 0;
        $familyIds = $this->auditLineageCandidates()
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        foreach ($familyIds as $familyId) {
            DB::transaction(function () use ($familyId, &$count): void {
                $family = DocumentFamily::query()->whereKey($familyId)->lockForUpdate()->firstOrFail();
                $alreadyRecorded = DocumentGovernanceAuditEvent::query()
                    ->where('document_family_id', $family->id)
                    ->where('system_actor_code', DocumentGovernanceSystemActorCode::AuditTargetScopeBackfill->value)
                    ->exists();

                if ($alreadyRecorded) {
                    return;
                }

                $this->audit->recordSystemFamily(
                    $family,
                    DocumentGovernanceSystemActorCode::AuditTargetScopeBackfill,
                    'document_audit_target_scope_backfilled',
                    [],
                    ['target_model' => 'family_or_version-v1'],
                );
                $count++;
            });
        }

        return $count;
    }

    /** @return iterable<int, Document> */
    private function pendingDocuments(int $batchSize): iterable
    {
        return Document::query()
            ->where('checksum_verification_status', ChecksumVerificationStatus::Pending->value)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();
    }

    /** @return null|'checksums_verified'|'checksums_unavailable'|'checksums_retryable' */
    private function backfillChecksum(Document $document): ?string
    {
        try {
            $identity = $this->storage->streamedIdentity($document);
        } catch (DocumentUploadException) {
            return 'checksums_retryable';
        }

        return DB::transaction(function () use ($document, $identity): string {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($locked->checksum_verification_status !== ChecksumVerificationStatus::Pending) {
                return null;
            }

            if ($identity === null || $identity['size_bytes'] !== $locked->size_bytes) {
                $reason = $identity === null
                    ? ($locked->status === DocumentStatus::Deleted
                        ? ChecksumUnavailableReason::SourceDeleted
                        : ChecksumUnavailableReason::SourceMissing)
                    : ChecksumUnavailableReason::SourceUnrecoverable;
                $locked->checksum_verification_status = ChecksumVerificationStatus::Unavailable;
                $locked->checksum_unavailable_reason = $reason;
                $locked->source_checksum_sha256 = null;
                $locked->save();
                $this->audit->recordSystem(
                    $locked,
                    DocumentGovernanceSystemActorCode::ChecksumBackfill,
                    'document_checksum_backfilled',
                    ['checksum_verification_status' => ChecksumVerificationStatus::Pending->value],
                    [
                        'checksum_verification_status' => ChecksumVerificationStatus::Unavailable->value,
                        'checksum_unavailable_reason' => $reason->value,
                        'algorithm' => self::CHECKSUM_ALGORITHM,
                    ],
                );

                return 'checksums_unavailable';
            }

            $locked->source_checksum_sha256 = $identity['sha256'];
            $locked->checksum_verification_status = ChecksumVerificationStatus::Verified;
            $locked->checksum_unavailable_reason = null;
            $locked->save();
            $this->audit->recordSystem(
                $locked,
                DocumentGovernanceSystemActorCode::ChecksumBackfill,
                'document_checksum_backfilled',
                ['checksum_verification_status' => ChecksumVerificationStatus::Pending->value],
                [
                    'checksum_verification_status' => ChecksumVerificationStatus::Verified->value,
                    'source_checksum_sha256' => $identity['sha256'],
                    'algorithm' => self::CHECKSUM_ALGORITHM,
                ],
            );

            return 'checksums_verified';
        });
    }

    private function finaliseOwnerConstraintWhenComplete(): void
    {
        if (DocumentFamily::query()->whereNull('owner_user_id')->exists() || DB::getDriverName() !== 'pgsql') {
            return;
        }

        $nullable = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'document_families')
            ->where('column_name', 'owner_user_id')
            ->value('is_nullable');

        if ($nullable === 'NO') {
            return;
        }

        $constraintExists = DB::table('pg_constraint')
            ->where('conname', 'document_families_owner_required_check')
            ->exists();

        if ($constraintExists) {
            DB::statement('ALTER TABLE document_families VALIDATE CONSTRAINT document_families_owner_required_check');
        }

        DB::statement('ALTER TABLE document_families ALTER COLUMN owner_user_id SET NOT NULL');
        DB::statement('ALTER TABLE document_families DROP CONSTRAINT IF EXISTS document_families_owner_required_check');
    }

    private function remainingCount(): int
    {
        return DocumentFamily::query()->whereNull('owner_user_id')->count()
            + Document::query()->where('checksum_verification_status', ChecksumVerificationStatus::Pending->value)->count()
            + $this->titleCandidates()->count()
            + $this->auditLineageCandidates()->count();
    }

    /** @return Builder<DocumentFamily> */
    private function titleCandidates(): Builder
    {
        return DocumentFamily::query()
            ->whereDoesntHave('governanceAuditEvents', fn (Builder $query): Builder => $query
                ->where('system_actor_code', DocumentGovernanceSystemActorCode::TitleBackfill->value))
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('documents')
                    ->whereColumn('documents.document_family_id', 'document_families.id')
                    ->whereNull('documents.predecessor_document_id')
                    ->whereColumn('documents.source_filename', 'document_families.name');
            });
    }

    /** @return Builder<DocumentFamily> */
    private function auditLineageCandidates(): Builder
    {
        return DocumentFamily::query()
            ->whereHas('governanceAuditEvents')
            ->whereDoesntHave('governanceAuditEvents', fn (Builder $query): Builder => $query
                ->where('system_actor_code', DocumentGovernanceSystemActorCode::AuditTargetScopeBackfill->value));
    }
}
