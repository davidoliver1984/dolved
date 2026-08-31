<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Support\Documents\CreateApplicabilitySnapshot;
use App\Support\Documents\MaintainDocumentFamilyActivitySummary;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateDocumentVersion
{
    public function __construct(
        private CreateApplicabilitySnapshot $createApplicabilitySnapshot,
        private ?MaintainDocumentFamilyActivitySummary $activity = null,
    ) {}

    /** @param null|array<int, OrganisationalLocation> $applicabilityLocations */
    public function handle(
        Document $predecessor,
        User $creator,
        string $sourceFilename,
        string $mediaType,
        int $sizeBytes,
        CarbonInterface $effectiveFrom,
        ?array $applicabilityLocations = null,
        ?string $extension = null,
    ): Document {
        return DB::transaction(function () use ($predecessor, $creator, $sourceFilename, $mediaType, $sizeBytes, $effectiveFrom, $applicabilityLocations, $extension): Document {
            $family = DocumentFamily::query()->whereKey($predecessor->document_family_id)->lockForUpdate()->firstOrFail();
            $locked = Document::query()->with('applicabilitySnapshot.locations')->lockForUpdate()->findOrFail($predecessor->id);

            if (Document::query()->where('predecessor_document_id', $locked->id)->exists()) {
                throw new DocumentGovernanceException('A document version cannot have more than one successor.');
            }
            if ($effectiveFrom->lte($locked->effective_from)) {
                throw new DocumentGovernanceException('A successor must have a later effective date than its predecessor.');
            }
            if (trim($sourceFilename) === '' || trim($mediaType) === '' || $sizeBytes < 0) {
                throw new DocumentGovernanceException('A version requires valid source metadata.');
            }
            if ($extension !== null && preg_match('/^[a-z0-9]+$/', $extension) !== 1) {
                throw new DocumentGovernanceException('The storage extension is invalid.');
            }

            $publicId = (string) Str::uuid();
            $document = new Document([
                'source_filename' => trim($sourceFilename),
                'publisher_label' => $locked->publisher_label,
                'source_url' => $locked->source_url,
                'media_type' => trim($mediaType),
                'size_bytes' => $sizeBytes,
            ]);
            $document->public_id = $publicId;
            $document->workspace_id = $locked->workspace_id;
            $document->document_family_id = $locked->document_family_id;
            $document->predecessor_document_id = $locked->id;
            $document->created_by_user_id = $creator->id;
            $document->status = DocumentStatus::Uploading;
            $document->governance_status = DocumentGovernanceStatus::Draft;
            $document->effective_from = $effectiveFrom;
            $baseKey = sprintf('workspaces/%s/documents/%s/source', $locked->workspace->public_id, $publicId);
            $document->storage_key = $extension === null ? $baseKey : $baseKey.'.'.$extension;
            $document->save();
            ($this->activity ?? app(MaintainDocumentFamilyActivitySummary::class))->record($family, $document->created_at);

            $locations = $applicabilityLocations ?? $locked->applicabilitySnapshot->locations->all();
            $this->createApplicabilitySnapshot->create($document, $locations);

            return $document->load(['family', 'predecessor', 'applicabilitySnapshot.locations']);
        });
    }

    /** @param array<int, OrganisationalLocation> $applicabilityLocations */
    public function handleVerifiedPromotion(
        DocumentFamily $family,
        ?Document $predecessor,
        User $creator,
        string $sourceFilename,
        ?string $publisherLabel,
        ?string $sourceUrl,
        string $mediaType,
        int $sizeBytes,
        CarbonInterface $effectiveFrom,
        string $storageKey,
        string $storageVersionId,
        string $sourceChecksumSha256,
        array $applicabilityLocations,
    ): Document {
        return DB::transaction(function () use ($family, $predecessor, $creator, $sourceFilename, $publisherLabel, $sourceUrl, $mediaType, $sizeBytes, $effectiveFrom, $storageKey, $storageVersionId, $sourceChecksumSha256, $applicabilityLocations): Document {
            $lockedFamily = DocumentFamily::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $lockedPredecessor = $predecessor === null
                ? null
                : Document::query()->lockForUpdate()->findOrFail($predecessor->id);

            if ($lockedPredecessor !== null) {
                if ($lockedPredecessor->document_family_id !== $lockedFamily->id
                    || Document::query()->where('predecessor_document_id', $lockedPredecessor->id)->exists()) {
                    throw new DocumentGovernanceException('The selected predecessor is no longer current.');
                }
                if ($effectiveFrom->lte($lockedPredecessor->effective_from)) {
                    throw new DocumentGovernanceException('A successor must have a later effective date than its predecessor.');
                }
            } elseif (Document::query()->where('document_family_id', $lockedFamily->id)->exists()) {
                throw new DocumentGovernanceException('A new document family cannot already contain a version.');
            }
            if (trim($sourceFilename) === '' || trim($mediaType) === '' || $sizeBytes < 0
                || trim($storageKey) === '' || trim($storageVersionId) === ''
                || preg_match('/^[a-f0-9]{64}$/', $sourceChecksumSha256) !== 1) {
                throw new DocumentGovernanceException('A verified promotion requires valid source identity.');
            }

            $document = new Document([
                'source_filename' => trim($sourceFilename),
                'publisher_label' => $publisherLabel,
                'source_url' => $sourceUrl,
                'media_type' => trim($mediaType),
                'size_bytes' => $sizeBytes,
            ]);
            $document->public_id = (string) Str::uuid();
            $document->workspace_id = $lockedFamily->workspace_id;
            $document->document_family_id = $lockedFamily->id;
            $document->predecessor_document_id = $lockedPredecessor?->id;
            $document->created_by_user_id = $creator->id;
            $document->status = DocumentStatus::Uploaded;
            $document->governance_status = DocumentGovernanceStatus::Draft;
            $document->effective_from = $effectiveFrom;
            $document->storage_key = $storageKey;
            $document->storage_version_id = $storageVersionId;
            $document->source_checksum_sha256 = $sourceChecksumSha256;
            $document->checksum_verification_status = ChecksumVerificationStatus::Verified;
            $document->save();

            ($this->activity ?? app(MaintainDocumentFamilyActivitySummary::class))->record($lockedFamily, $document->created_at);
            $this->createApplicabilitySnapshot->create($document, $applicabilityLocations);

            return $document->load(['family', 'predecessor', 'applicabilitySnapshot.locations']);
        });
    }
}
