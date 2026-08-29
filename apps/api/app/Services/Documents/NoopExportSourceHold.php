<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\Documents\ExportSourceHold;
use App\Models\Document;

final class NoopExportSourceHold implements ExportSourceHold
{
    public function blocksPhysicalRemoval(Document $lockedDocument): bool
    {
        return false;
    }
}
