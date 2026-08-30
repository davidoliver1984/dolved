<?php

declare(strict_types=1);

namespace App\Actions\Documents;

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
}
