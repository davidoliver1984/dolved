<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\ContentCloneManifestStatus;
use App\Enums\ExtractionUploadCleanupState;
use App\Enums\IngestionAttemptStatus;
use App\Models\DocumentContentCloneManifest;
use App\Services\Documents\ExtractionArtifactObjectStorage;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class SweepContentCloneManifests
{
    public function __construct(private ExtractionArtifactObjectStorage $storage) {}

    /** @return array{claimed: int, deleted: int, failed: int, protected: int} */
    public function handle(?int $limit = null): array
    {
        $batchSize = min(500, max(1, $limit ?? (int) config('ingestion.orchestration.content_clone_manifest_cleanup_batch_size')));
        $ids = DocumentContentCloneManifest::query()
            ->whereIn('cleanup_state', [
                ExtractionUploadCleanupState::NotNeeded->value,
                ExtractionUploadCleanupState::Eligible->value,
                ExtractionUploadCleanupState::Failed->value,
            ])
            ->where(function ($query): void {
                $query->where('cleanup_state', ExtractionUploadCleanupState::Eligible->value)
                    ->orWhere('expires_at', '<=', now())
                    ->orWhere(function ($failed): void {
                        $failed->where('cleanup_state', ExtractionUploadCleanupState::Failed->value)
                            ->where('cleanup_attempt_count', '<', max(1, (int) config('ingestion.orchestration.content_clone_manifest_cleanup_max_attempts')));
                    });
            })
            ->orderBy('id')->limit($batchSize)->pluck('id');

        $result = ['claimed' => 0, 'deleted' => 0, 'failed' => 0, 'protected' => 0];
        foreach ($ids as $id) {
            $manifest = DB::transaction(function () use ($id): ?DocumentContentCloneManifest {
                $candidate = DocumentContentCloneManifest::query()->lockForUpdate()->find($id);
                if ($candidate === null || ! in_array($candidate->cleanup_state, [ExtractionUploadCleanupState::NotNeeded, ExtractionUploadCleanupState::Eligible, ExtractionUploadCleanupState::Failed], true)) {
                    return null;
                }
                $attempt = $candidate->attempt()->lockForUpdate()->firstOrFail();
                $terminal = in_array($attempt->status, [
                    IngestionAttemptStatus::Completed,
                    IngestionAttemptStatus::Failed,
                    IngestionAttemptStatus::Cancelled,
                ], true);
                $expiredGeneration = $attempt->lease_generation !== $candidate->lease_generation
                    || $attempt->lease_expires_at === null
                    || $attempt->lease_expires_at->isPast();
                if (! $terminal && ! $expiredGeneration) {
                    return null;
                }
                if ($candidate->status === ContentCloneManifestStatus::Verified && ! $expiredGeneration) {
                    return null;
                }
                $candidate->forceFill([
                    'cleanup_state' => ExtractionUploadCleanupState::Claimed,
                    'cleanup_claimed_at' => now(),
                    'cleanup_error_code' => null,
                ])->save();

                return $candidate;
            });
            if ($manifest === null) {
                $result['protected']++;

                continue;
            }
            $result['claimed']++;
            try {
                $this->storage->deleteManifestExact($manifest->object_key);
                $manifest->refresh()->forceFill([
                    'cleanup_state' => ExtractionUploadCleanupState::Deleted,
                    'cleanup_attempt_count' => $manifest->cleanup_attempt_count + 1,
                    'cleanup_last_attempted_at' => now(),
                ])->save();
                $result['deleted']++;
            } catch (Throwable) {
                $manifest->refresh();
                $attempts = $manifest->cleanup_attempt_count + 1;
                $manifest->forceFill([
                    'cleanup_state' => $attempts >= max(1, (int) config('ingestion.orchestration.content_clone_manifest_cleanup_max_attempts'))
                        ? ExtractionUploadCleanupState::Failed
                        : ExtractionUploadCleanupState::Eligible,
                    'cleanup_attempt_count' => $attempts,
                    'cleanup_last_attempted_at' => now(),
                    'cleanup_error_code' => 'clone_manifest_delete_failed',
                ])->save();
                $result['failed']++;
            }
        }

        return $result;
    }
}
