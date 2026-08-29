<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\DocumentObjectStorage;
use Illuminate\Support\Facades\DB;

class InitializeDocumentUpload
{
    public function __construct(
        private readonly CreateDocument $createDocument,
        private readonly DocumentObjectStorage $storage,
    ) {}

    /**
     * @return array{
     *     document: Document,
     *     upload: array{
     *         url: string,
     *         method: 'PUT',
     *         headers: array<string, string>,
     *         expires_at: string
     *     }
     * }
     */
    public function handle(
        Workspace $workspace,
        User $creator,
        string $sourceFilename,
        string $mediaType,
        int $sizeBytes,
        string $extension,
        ?string $publisherLabel = null,
        ?string $sourceUrl = null,
    ): array {
        return DB::transaction(function () use (
            $workspace,
            $creator,
            $sourceFilename,
            $mediaType,
            $sizeBytes,
            $extension,
            $publisherLabel,
            $sourceUrl,
        ): array {
            $document = $this->createDocument->handle(
                $workspace,
                $creator,
                $sourceFilename,
                $mediaType,
                $sizeBytes,
                $extension,
                $publisherLabel,
                $sourceUrl,
            );

            return [
                'document' => $document,
                'upload' => $this->storage->createUploadRequest($document),
            ];
        });
    }
}
