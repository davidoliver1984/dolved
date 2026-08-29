<?php

declare(strict_types=1);

namespace App\Contracts\Ingestion;

use App\Models\DocumentContentCloneManifest;
use App\Models\DocumentContentCloneOperation;

interface ContentCloneVectorGateway
{
    /** @return array{complete: bool, point_count: int, point_manifest_digest: string} */
    public function clone(
        DocumentContentCloneOperation $operation,
        DocumentContentCloneManifest $manifest,
        string $leaseToken,
    ): array;

    public function cleanup(DocumentContentCloneOperation $operation): bool;
}
