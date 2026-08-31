<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\LegacyUploadCutoverException;
use App\Models\Document;
use App\Models\LegacyUploadInitializationGate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\DocumentObjectStorage;
use App\Support\Imports\LegacyUploadCutoverAudit;
use Illuminate\Support\Facades\DB;

class InitializeDocumentUpload
{
    public function __construct(
        private readonly CreateDocument $createDocument,
        private readonly DocumentObjectStorage $storage,
        private readonly LegacyUploadCutoverAudit $cutoverAudits,
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
            $gate = LegacyUploadInitializationGate::query()->lockForUpdate()->findOrFail(1);
            if ($gate->closed) {
                throw LegacyUploadCutoverException::routeClosed();
            }
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
            $document->legacy_upload_initiated_before_cutover = true;
            $document->legacy_upload_cutover_operation_id = $gate->cutover_operation_id;
            $document->save();
            $this->cutoverAudits->recordHuman($document, $gate, $creator);
            $gate->total_marked_count++;
            $gate->save();

            return [
                'document' => $document,
                'upload' => $this->storage->createUploadRequest($document),
            ];
        });
    }
}
