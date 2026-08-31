<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\DocumentStatus;
use App\Exceptions\LegacyUploadCutoverException;
use App\Models\Document;
use App\Models\LegacyUploadInitializationGate;
use App\Support\Imports\LegacyUploadCutoverAudit;
use Illuminate\Support\Facades\DB;

final readonly class InventoryLegacyUploads
{
    public function __construct(private LegacyUploadCutoverAudit $audits) {}

    public function handle(int $limit): int
    {
        $limit = max(1, min((int) config('imports.legacy_cutover.max_batch_size'), $limit));

        return DB::transaction(function () use ($limit): int {
            $gate = LegacyUploadInitializationGate::query()->lockForUpdate()->findOrFail(1);
            if ($gate->closed) {
                return 0;
            }
            $documents = Document::query()
                ->where('id', '>', $gate->inventory_cursor_id)
                ->whereNull('legacy_upload_initiated_before_cutover')
                ->whereIn('status', [DocumentStatus::Uploading->value, DocumentStatus::Uploaded->value])
                ->orderBy('id')->limit($limit)->lockForUpdate()->get();
            foreach ($documents as $document) {
                $this->mark($document, $gate, 'inventory_backfill');
            }
            if ($documents->isNotEmpty()) {
                $gate->inventory_cursor_id = (int) $documents->last()->id;
                $gate->save();
            }

            return $documents->count();
        });
    }

    public function mark(Document $document, LegacyUploadInitializationGate $gate, string $reason): void
    {
        if ($document->legacy_upload_cutover_operation_id !== null) {
            if ($document->legacy_upload_cutover_operation_id !== $gate->cutover_operation_id) {
                throw LegacyUploadCutoverException::markerConflict();
            }

            return;
        }
        DB::table('documents')->where('id', $document->id)->update([
            'legacy_upload_initiated_before_cutover' => true,
            'legacy_upload_cutover_operation_id' => $gate->cutover_operation_id,
        ]);
        $document->forceFill([
            'legacy_upload_initiated_before_cutover' => true,
            'legacy_upload_cutover_operation_id' => $gate->cutover_operation_id,
        ]);
        $this->audits->recordSystem($document, $gate, $reason);
        $gate->total_marked_count++;
    }
}
