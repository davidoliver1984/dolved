<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Workspace;
use App\Services\Documents\ImportStagingStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ReplaceImportItem
{
    public function __construct(private ImportStagingStorage $storage) {}

    /** @return array{item: ImportItem, upload: array<string, mixed>} */
    public function handle(Workspace $workspace, ImportItem $original, string $filename, string $mediaType): array
    {
        $replacement = DB::transaction(function () use ($workspace, $original, $filename, $mediaType): ImportItem {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($original->import_batch_id);
            $locked = ImportItem::query()->lockForUpdate()->findOrFail($original->id);

            if ($locked->workspace_id !== $workspace->id
                || $batch->workspace_id !== $workspace->id
                || $batch->status !== ImportBatchStatus::Open
                || $locked->replaced_by_import_item_id !== null
                || $locked->promotionAttempts()->exists()
                || $batch->items()->count() >= 50) {
                throw new RuntimeException('This import item cannot be replaced.');
            }

            $publicId = (string) Str::uuid();
            $replacement = ImportItem::query()->create([
                'public_id' => $publicId,
                'import_batch_id' => $locked->import_batch_id,
                'workspace_id' => $locked->workspace_id,
                'staged_object_key' => sprintf(
                    '%s/%s/items/%s/source',
                    trim((string) config('imports.staging_prefix'), '/'),
                    $workspace->public_id,
                    $publicId,
                ),
                'source_filename' => $filename,
                'declared_media_type' => $mediaType,
                'preflight_status' => ImportPreflightStatus::Pending,
                'match_status' => ImportMatchStatus::Pending,
            ]);

            $locked->forceFill(['replaced_by_import_item_id' => $replacement->id])->save();

            return $replacement;
        });

        return [
            'item' => $replacement,
            'upload' => $this->storage->createUploadRequest($workspace, $replacement, $mediaType),
        ];
    }
}
