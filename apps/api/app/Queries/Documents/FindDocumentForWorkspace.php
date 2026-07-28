<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Models\Document;
use App\Models\Workspace;

class FindDocumentForWorkspace
{
    public function handle(Workspace $workspace, string $publicId): Document
    {
        return $workspace->documents()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
