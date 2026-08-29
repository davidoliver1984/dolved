<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;
use App\Models\Workspace;
use App\Support\Documents\NormaliseDocumentMetadataName;
use Illuminate\Validation\ValidationException;

final class CreateDocumentCategory
{
    public function handle(Workspace $workspace, string $name): DocumentCategory
    {
        if ($workspace->documentCategories()->where('normalised_name', NormaliseDocumentMetadataName::handle($name))->exists()) {
            throw ValidationException::withMessages(['name' => 'A category with this name already exists.']);
        }

        $category = new DocumentCategory([
            'name' => trim($name),
            'status' => DocumentCategoryStatus::Active,
        ]);
        $category->workspace()->associate($workspace);
        $category->save();

        return $category;
    }
}
