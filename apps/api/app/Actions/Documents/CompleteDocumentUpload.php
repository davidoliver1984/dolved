<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\ChecksumVerificationStatus;
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

        $identity = $this->storage->streamedIdentity($document);

        if ($identity === null) {
            throw DocumentUploadException::missingObject();
        }

        if ($identity['size_bytes'] !== $document->size_bytes) {
            throw DocumentUploadException::sizeMismatch();
        }

        return DB::transaction(function () use ($document, $identity): Document {
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

            $lockedDocument->source_checksum_sha256 = $identity['sha256'];
            $lockedDocument->checksum_verification_status = ChecksumVerificationStatus::Verified;
            $lockedDocument->checksum_unavailable_reason = null;
            $lockedDocument->status = DocumentStatus::Uploaded;
            $lockedDocument->save();

            return $lockedDocument->refresh();
        });
    }
}
