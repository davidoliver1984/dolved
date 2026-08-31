<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\ImportItem;
use App\Models\Workspace;
use LogicException;

final class ImportStagingObjectKey
{
    public function for(Workspace $workspace, ImportItem $item): string
    {
        if ((int) $item->workspace_id !== (int) $workspace->id) {
            throw new LogicException('The import item does not belong to this workspace.');
        }

        return sprintf(
            '%s/%s/items/%s/source',
            trim((string) config('imports.staging_prefix'), '/'),
            $workspace->public_id,
            $item->public_id,
        );
    }

    public function assertExact(Workspace $workspace, ImportItem $item): string
    {
        $expected = $this->for($workspace, $item);

        if (! hash_equals($expected, (string) $item->staged_object_key)) {
            throw new LogicException('The staged object key is not bound to this workspace and item.');
        }

        return $expected;
    }
}
