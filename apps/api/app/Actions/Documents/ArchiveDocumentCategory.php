<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Support\Documents\RecordLibrarySettingsAudit;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveDocumentCategory
{
    public function __construct(private RecordLibrarySettingsAudit $audit) {}

    public function handle(DocumentCategory $category, User $actor): DocumentCategory
    {
        return DB::transaction(function () use ($category, $actor): DocumentCategory {
            $locked = DocumentCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status->value;
            $locked->status = DocumentCategoryStatus::Archived;
            $locked->save();
            $this->audit->handle(
                $locked->workspace,
                $actor,
                'document_category',
                $locked->public_id,
                'document_category_archived',
                ['status' => $previous],
                ['status' => $locked->status->value],
            );

            return $locked->refresh();
        });
    }
}
