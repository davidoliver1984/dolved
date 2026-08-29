<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

use App\Models\Document;

interface ExportSourceHold
{
    /**
     * Reserved ADR-0037 coordination point. The caller must hold documents.id.
     */
    public function blocksPhysicalRemoval(Document $lockedDocument): bool;
}
