<?php

declare(strict_types=1);

namespace App\Support\Imports;

use App\Exceptions\LegacyUploadCutoverException;
use App\Models\Document;
use App\Models\LegacyUploadInitializationGate;

final class LegacyUploadRouteGate
{
    public function assertContinuationAllowed(Document $document): void
    {
        $gate = LegacyUploadInitializationGate::query()->findOrFail(1);
        if ($gate->drain_closed_at !== null) {
            throw LegacyUploadCutoverException::routeClosed();
        }
        if (! $gate->closed && $document->legacy_upload_initiated_before_cutover === null) {
            return;
        }
        if ($document->legacy_upload_initiated_before_cutover !== true
            || $document->legacy_upload_cutover_operation_id !== $gate->cutover_operation_id) {
            throw LegacyUploadCutoverException::markerConflict();
        }
    }
}
