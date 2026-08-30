<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\DocumentCategory;
use App\Models\User;
use App\Support\Documents\NormaliseDocumentMetadataName;
use App\Support\Documents\RecordLibrarySettingsAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RenameDocumentCategory
{
    public function __construct(private RecordLibrarySettingsAudit $audit) {}

    public function handle(DocumentCategory $category, User $actor, string $name): DocumentCategory
    {
        return DB::transaction(function () use ($category, $actor, $name): DocumentCategory {
            $locked = DocumentCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $normalised = NormaliseDocumentMetadataName::handle($name);

            if (DocumentCategory::query()
                ->where('workspace_id', $locked->workspace_id)
                ->where('normalised_name', $normalised)
                ->whereKeyNot($locked->id)
                ->exists()) {
                throw ValidationException::withMessages(['name' => 'A category with this name already exists.']);
            }

            $previous = $locked->name;
            $locked->name = trim($name);
            $locked->save();
            $this->audit->handle(
                $locked->workspace,
                $actor,
                'document_category',
                $locked->public_id,
                'document_category_renamed',
                ['name' => $previous],
                ['name' => $locked->name],
            );

            return $locked->refresh();
        });
    }
}
