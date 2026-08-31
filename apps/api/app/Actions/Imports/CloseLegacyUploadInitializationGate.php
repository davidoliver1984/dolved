<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\LegacyUploadCutoverEvent;
use App\Models\LegacyUploadInitializationGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CloseLegacyUploadInitializationGate
{
    public function __construct(private InventoryLegacyUploads $inventory) {}

    public function handle(int $finalRemainderLimit): bool
    {
        $limit = max(1, min((int) config('imports.legacy_cutover.max_batch_size'), $finalRemainderLimit));

        return DB::transaction(function () use ($limit): bool {
            $gate = LegacyUploadInitializationGate::query()->lockForUpdate()->findOrFail(1);
            if ($gate->closed) {
                return true;
            }
            $remainder = Document::query()->whereNull('legacy_upload_initiated_before_cutover')
                ->whereIn('status', [DocumentStatus::Uploading->value, DocumentStatus::Uploaded->value])
                ->orderBy('id')->limit($limit + 1)->lockForUpdate()->get();
            if ($remainder->count() > $limit) {
                return false;
            }
            foreach ($remainder as $document) {
                $this->inventory->mark($document, $gate, 'final_remainder');
            }
            $remaining = Document::query()->whereNull('legacy_upload_initiated_before_cutover')
                ->whereIn('status', [DocumentStatus::Uploading->value, DocumentStatus::Uploaded->value])->count();
            if ($remaining !== 0) {
                return false;
            }
            $gate->closed = true;
            $gate->closed_at = now();
            $gate->save();
            LegacyUploadCutoverEvent::query()->firstOrCreate(
                ['cutover_operation_id' => $gate->cutover_operation_id, 'event_type' => 'gate_closed'],
                ['public_id' => (string) Str::uuid(), 'total_marked_count' => $gate->total_marked_count, 'occurred_at' => now()],
            );

            return true;
        });
    }
}
