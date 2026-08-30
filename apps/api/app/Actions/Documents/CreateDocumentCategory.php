<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Documents\NormaliseDocumentMetadataName;
use App\Support\Documents\RecordLibrarySettingsAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateDocumentCategory
{
    public function __construct(private RecordLibrarySettingsAudit $audit) {}

    public function handle(Workspace $workspace, User $actor, string $name): DocumentCategory
    {
        if ($workspace->documentCategories()->where('normalised_name', NormaliseDocumentMetadataName::handle($name))->exists()) {
            throw ValidationException::withMessages(['name' => 'A category with this name already exists.']);
        }

        return DB::transaction(function () use ($workspace, $actor, $name): DocumentCategory {
            $category = new DocumentCategory([
                'name' => trim($name),
                'status' => DocumentCategoryStatus::Active,
            ]);
            $category->workspace()->associate($workspace);
            $category->save();
            $this->audit->handle(
                $workspace,
                $actor,
                'document_category',
                $category->public_id,
                'document_category_created',
                [],
                ['name' => $category->name, 'status' => $category->status->value],
            );

            return $category;
        });
    }
}
