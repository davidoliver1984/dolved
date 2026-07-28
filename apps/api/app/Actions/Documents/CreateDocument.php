<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateDocument
{
    public function handle(
        Workspace $workspace,
        User $creator,
        string $sourceFilename,
        string $mediaType,
        int $sizeBytes,
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

        $publicId = (string) Str::uuid();
        $document = new Document([
            'source_filename' => $sourceFilename,
            'media_type' => $mediaType,
            'size_bytes' => $sizeBytes,
        ]);
        $document->public_id = $publicId;
        $document->status = DocumentStatus::Uploading;
        $document->storage_key = sprintf(
            'workspaces/%s/documents/%s/source',
            $workspace->public_id,
            $publicId,
        );
        $document->workspace()->associate($workspace);
        $document->createdBy()->associate($creator);
        $document->save();

        return $document->load(['workspace', 'createdBy']);
    }
}
