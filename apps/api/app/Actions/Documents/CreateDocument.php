<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Documents\CreateApplicabilitySnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateDocument
{
    public function __construct(private readonly CreateApplicabilitySnapshot $createApplicabilitySnapshot) {}

    public function handle(
        Workspace $workspace,
        User $creator,
        string $sourceFilename,
        string $mediaType,
        int $sizeBytes,
        ?string $extension = null,
    ): Document {
        $sourceFilename = trim($sourceFilename);
        $mediaType = trim($mediaType);

        if ($sourceFilename === '') {
            throw new InvalidArgumentException('A source filename is required.');
        }

        if ($mediaType === '') {
            throw new InvalidArgumentException('A media type is required.');
        }

        if ($sizeBytes < 0) {
            throw new InvalidArgumentException('Document size cannot be negative.');
        }

        if (
            $extension !== null
            && preg_match('/^[a-z0-9]+$/', $extension) !== 1
        ) {
            throw new InvalidArgumentException('The storage extension is invalid.');
        }

        return DB::transaction(function () use ($workspace, $creator, $sourceFilename, $mediaType, $sizeBytes, $extension): Document {
            $family = new DocumentFamily([
                'name' => $sourceFilename,
                'owner_user_id' => $creator->id,
            ]);
            $family->public_id = (string) Str::uuid();
            $family->workspace()->associate($workspace);
            $family->save();

            $publicId = (string) Str::uuid();
            $document = new Document([
                'source_filename' => $sourceFilename,
                'media_type' => $mediaType,
                'size_bytes' => $sizeBytes,
            ]);
            $document->public_id = $publicId;
            $document->status = DocumentStatus::Uploading;
            $document->governance_status = DocumentGovernanceStatus::Draft;
            $document->effective_from = now();
            $storageKey = sprintf('workspaces/%s/documents/%s/source', $workspace->public_id, $publicId);
            $document->storage_key = $extension === null ? $storageKey : $storageKey.'.'.$extension;
            $document->workspace()->associate($workspace);
            $document->family()->associate($family);
            $document->createdBy()->associate($creator);
            $document->save();

            $defaults = $family->defaultApplicabilityLocations()->get()->all();
            $this->createApplicabilitySnapshot->create($document, $defaults);

            return $document->load(['workspace', 'createdBy', 'family', 'applicabilitySnapshot.locations']);
        });
    }
}
