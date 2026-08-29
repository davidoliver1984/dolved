<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;

final class ArchiveDocumentCategory
{
    public function handle(DocumentCategory $category): DocumentCategory
    {
        $category->status = DocumentCategoryStatus::Archived;
        $category->save();

        return $category->refresh();
    }
}
