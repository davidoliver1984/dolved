<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\DocumentStatus;
use App\Exceptions\LegacyUploadCutoverException;
use App\Models\Document;
use App\Models\LegacyUploadCutoverEvent;
use App\Models\LegacyUploadInitializationGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ReconcileLegacyUploadDrain
{
    public function __construct(private ExpireLegacyDrainUpload $expire) {}

    /** @return array{expired: int, remaining: int, drain_closed: bool} */
    public function handle(int $limit): array
    {
        $limit = max(1, min((int) config('imports.legacy_cutover.max_batch_size'), $limit));
        $gate = LegacyUploadInitializationGate::query()->findOrFail(1);
        if (! $gate->closed) {
            throw LegacyUploadCutoverException::markerConflict();
        }
        $cutoff = now()->subHours(max(1, (int) config('imports.legacy_cutover.drain_window_hours')));
        $candidates = Document::query()
            ->where('legacy_upload_initiated_before_cutover', true)
            ->where('legacy_upload_cutover_operation_id', $gate->cutover_operation_id)
            ->whereIn('status', [DocumentStatus::Uploading->value, DocumentStatus::Uploaded->value])
            ->where('updated_at', '<=', $cutoff)->orderBy('id')->limit($limit)->get();
        $expired = 0;
        foreach ($candidates as $candidate) {
            try {
                $this->expire->handle($candidate);
                $expired++;
            } catch (LegacyUploadCutoverException) {
                // A concurrent completion/ingestion request won; the final count below is authoritative.
            }
        }

        return DB::transaction(function () use ($expired): array {
            $gate = LegacyUploadInitializationGate::query()->lockForUpdate()->findOrFail(1);
            $unmarked = Document::query()->whereNull('legacy_upload_initiated_before_cutover')
                ->whereIn('status', [DocumentStatus::Uploading->value, DocumentStatus::Uploaded->value])->count();
            if ($unmarked !== 0) {
                throw LegacyUploadCutoverException::markerConflict();
            }
            $remaining = Document::query()
                ->where('legacy_upload_initiated_before_cutover', true)
                ->where('legacy_upload_cutover_operation_id', $gate->cutover_operation_id)
                ->whereIn('status', [DocumentStatus::Uploading->value, DocumentStatus::Uploaded->value])->count();
            if ($remaining === 0 && $gate->drain_closed_at === null) {
                $gate->drain_closed_at = now();
                $gate->save();
                LegacyUploadCutoverEvent::query()->firstOrCreate(
                    ['cutover_operation_id' => $gate->cutover_operation_id, 'event_type' => 'drain_closed'],
                    ['public_id' => (string) Str::uuid(), 'total_marked_count' => $gate->total_marked_count, 'occurred_at' => now()],
                );
            }

            return ['expired' => $expired, 'remaining' => $remaining, 'drain_closed' => $gate->drain_closed_at !== null];
        });
    }
}
