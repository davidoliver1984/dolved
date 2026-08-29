<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\DocumentTag;
use App\Models\Workspace;
use App\Support\Documents\NormaliseDocumentMetadataName;
use Illuminate\Validation\ValidationException;

final class CreateDocumentTag
{
    public function handle(Workspace $workspace, string $name): DocumentTag
    {
        if ($workspace->documentTags()->where('normalised_name', NormaliseDocumentMetadataName::handle($name))->exists()) {
            throw ValidationException::withMessages(['name' => 'A tag with this name already exists.']);
        }

        $tag = new DocumentTag(['name' => trim($name)]);
        $tag->workspace()->associate($workspace);
        $tag->save();

        return $tag;
    }
}
