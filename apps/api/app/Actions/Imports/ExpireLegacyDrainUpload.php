<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\DocumentStatus;
use App\Exceptions\LegacyUploadCutoverException;
use App\Models\Document;
use App\Models\LegacyUploadInitializationGate;
use App\Support\Documents\MaintainDocumentFamilyActivitySummary;
use Illuminate\Support\Facades\DB;

final readonly class ExpireLegacyDrainUpload
{
    public function __construct(private MaintainDocumentFamilyActivitySummary $activity) {}

    public function handle(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $gate = LegacyUploadInitializationGate::query()->lockForUpdate()->findOrFail(1);
            $locked = Document::query()->with('family')->lockForUpdate()->findOrFail($document->id);
            $cutoff = now()->subHours(max(1, (int) config('imports.legacy_cutover.drain_window_hours')));
            if (! $gate->closed || $gate->drain_closed_at !== null
                || $locked->legacy_upload_initiated_before_cutover !== true
                || $locked->legacy_upload_cutover_operation_id !== $gate->cutover_operation_id
                || ! in_array($locked->status, [DocumentStatus::Uploading, DocumentStatus::Uploaded], true)
                || $gate->closed_at?->greaterThan($cutoff)
                || $locked->updated_at->greaterThan($cutoff)) {
                throw LegacyUploadCutoverException::markerConflict();
            }
            $locked->status = DocumentStatus::Failed;
            $locked->failure_category = 'legacy_upload_drain_expired';
            $locked->failure_message = 'The pre-cutover upload did not complete within the bounded legacy drain window.';
            $locked->save();
            $this->activity->record($locked->family, $locked->updated_at);

            return $locked;
        });
    }
}
