<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Models\DocumentFamily;
use App\Models\Workspace;

final class FindDocumentFamilyForWorkspace
{
    public function handle(Workspace $workspace, string $publicId): DocumentFamily
    {
        return $workspace->documentFamilies()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
