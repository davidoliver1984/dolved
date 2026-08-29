<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use App\Enums\IngestionAttemptStatus;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Services\Documents\ExtractionArtifactObjectStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class SweepExtractionArtifactOrphans
{
    public function __construct(private readonly ExtractionArtifactObjectStorage $storage) {}

    /** @return array{claimed: int, deleted: int, failed: int, protected: int} */
    public function handle(?int $limit = null): array
    {
        $batchSize = min(500, max(1, $limit ?? (int) config('ingestion.orchestration.extraction_cleanup_batch_size')));
        $ids = DocumentExtractionUploadAuthorisation::query()
            ->where('status', '!=', ExtractionUploadStatus::Verified->value)
            ->whereIn('cleanup_state', [
                ExtractionUploadCleanupState::NotNeeded->value,
                ExtractionUploadCleanupState::Eligible->value,
            ])
            ->where(function (Builder $query): void {
                $query->where('cleanup_state', ExtractionUploadCleanupState::Eligible->value)
                    ->orWhere('expires_at', '<=', now())
                    ->orWhereHas('attempt', fn (Builder $attempt): Builder => $attempt->whereIn('status', [
                        IngestionAttemptStatus::Failed->value,
                        IngestionAttemptStatus::Cancelled->value,
                    ]));
            })
            ->whereHas('attempt', function (Builder $attempt): void {
                $attempt->whereIn('status', [
                    IngestionAttemptStatus::Failed->value,
                    IngestionAttemptStatus::Cancelled->value,
                    IngestionAttemptStatus::Completed->value,
                ])->orWhere('lease_expires_at', '<=', now());
            })
            ->orderBy('id')->limit($batchSize)->pluck('id');

        $result = ['claimed' => 0, 'deleted' => 0, 'failed' => 0, 'protected' => 0];
        foreach ($ids as $id) {
            $record = DB::transaction(function () use ($id): ?DocumentExtractionUploadAuthorisation {
                $candidate = DocumentExtractionUploadAuthorisation::query()->lockForUpdate()->find($id);
                if ($candidate === null || $candidate->status === ExtractionUploadStatus::Verified || ! in_array($candidate->cleanup_state, [ExtractionUploadCleanupState::NotNeeded, ExtractionUploadCleanupState::Eligible], true)) {
                    return null;
                }
                $attempt = $candidate->attempt()->lockForUpdate()->firstOrFail();
                if (! in_array($attempt->status, [IngestionAttemptStatus::Failed, IngestionAttemptStatus::Cancelled, IngestionAttemptStatus::Completed], true)
                    && $attempt->lease_generation === $candidate->lease_generation
                    && $attempt->lease_expires_at?->isFuture()) {
                    return null;
                }
                if (DocumentExtractionArtifact::query()->where('object_key', $candidate->object_key)->whereNotNull('published_at')->exists()) {
                    $candidate->forceFill(['cleanup_state' => ExtractionUploadCleanupState::NotNeeded])->save();

                    return null;
                }
                $candidate->forceFill([
                    'cleanup_state' => ExtractionUploadCleanupState::Claimed,
                    'cleanup_claimed_at' => now(),
                    'cleanup_error_code' => null,
                ])->save();

                return $candidate;
            });
            if ($record === null) {
                $result['protected']++;

                continue;
            }
            $result['claimed']++;
            try {
                $this->storage->deleteExact($record->object_key);
                DocumentExtractionUploadAuthorisation::query()->whereKey($record->id)
                    ->where('cleanup_state', ExtractionUploadCleanupState::Claimed->value)
                    ->update([
                        'cleanup_state' => ExtractionUploadCleanupState::Deleted->value,
                        'cleanup_attempt_count' => DB::raw('cleanup_attempt_count + 1'),
                        'cleanup_last_attempted_at' => now(),
                        'updated_at' => now(),
                    ]);
                $result['deleted']++;
            } catch (Throwable) {
                DB::transaction(function () use ($record): void {
                    $locked = DocumentExtractionUploadAuthorisation::query()->lockForUpdate()->findOrFail($record->id);
                    $attempts = $locked->cleanup_attempt_count + 1;
                    $locked->forceFill([
                        'cleanup_state' => $attempts >= max(1, (int) config('ingestion.orchestration.extraction_cleanup_max_attempts'))
                            ? ExtractionUploadCleanupState::Failed
                            : ExtractionUploadCleanupState::Eligible,
                        'cleanup_attempt_count' => $attempts,
                        'cleanup_last_attempted_at' => now(),
                        'cleanup_error_code' => 'artifact_delete_failed',
                    ])->save();
                });
                $result['failed']++;
            }
        }

        return $result;
    }
}
