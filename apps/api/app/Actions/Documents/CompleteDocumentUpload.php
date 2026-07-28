<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Exceptions\DocumentUploadException;
use App\Models\Document;
use App\Services\Documents\DocumentObjectStorage;
use Illuminate\Support\Facades\DB;

class CompleteDocumentUpload
{
    public function __construct(
        private readonly DocumentObjectStorage $storage,
    ) {}

    public function handle(Document $document): Document
    {
        if ($document->status === DocumentStatus::Uploaded) {
            return $document;
        }

        if ($document->status !== DocumentStatus::Uploading) {
            throw DocumentUploadException::invalidState();
        }

        $objectSize = $this->storage->objectSize($document);

        if ($objectSize === null) {
            throw DocumentUploadException::missingObject();
        }

        if ($objectSize !== $document->size_bytes) {
            throw DocumentUploadException::sizeMismatch();
        }

        return DB::transaction(function () use ($document): Document {
            $lockedDocument = Document::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDocument->status === DocumentStatus::Uploaded) {
                return $lockedDocument;
            }

            if ($lockedDocument->status !== DocumentStatus::Uploading) {
                throw DocumentUploadException::invalidState();
            }

            $lockedDocument->status = DocumentStatus::Uploaded;
            $lockedDocument->save();

            return $lockedDocument->refresh();
        });
    }
}
