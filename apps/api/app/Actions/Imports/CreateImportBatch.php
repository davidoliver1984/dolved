<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\ImportStagingStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateImportBatch
{
    public function __construct(private ImportStagingStorage $storage) {}

    /**
     * @param  list<array{filename: string, media_type: string, size_bytes: int}>  $files
     * @return array{batch: ImportBatch, items: list<array{item: ImportItem, upload: array<string, mixed>}>}
     */
    public function handle(Workspace $workspace, User $actor, array $files): array
    {
        $batch = DB::transaction(function () use ($workspace, $actor, $files): ImportBatch {
            $batch = ImportBatch::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'initiated_by_user_id' => $actor->id,
                'status' => ImportBatchStatus::Open,
                'retention_expires_at' => now()->addDays((int) config('imports.retention_days')),
            ]);

            foreach ($files as $file) {
                $publicId = (string) Str::uuid();
                ImportItem::query()->create([
                    'public_id' => $publicId,
                    'import_batch_id' => $batch->id,
                    'workspace_id' => $workspace->id,
                    'staged_object_key' => sprintf(
                        '%s/%s/items/%s/source',
                        trim((string) config('imports.staging_prefix'), '/'),
                        $workspace->public_id,
                        $publicId,
                    ),
                    'source_filename' => $file['filename'],
                    'declared_media_type' => $file['media_type'],
                    'preflight_status' => ImportPreflightStatus::Pending,
                    'match_status' => ImportMatchStatus::Pending,
                ]);
            }

            return $batch;
        });

        $items = [];
        foreach ($batch->items()->orderBy('id')->get() as $item) {
            $items[] = [
                'item' => $item,
                'upload' => $this->storage->createUploadRequest(
                    $workspace,
                    $item,
                    (string) $item->declared_media_type,
                ),
            ];
        }

        return ['batch' => $batch, 'items' => $items];
    }
}
