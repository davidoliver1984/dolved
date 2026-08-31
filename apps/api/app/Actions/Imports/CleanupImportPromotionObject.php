<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\PromotionAttemptStatus;
use App\Exceptions\ImportPromotionException;
use App\Models\Document;
use App\Models\PromotionAttempt;
use App\Services\Documents\ImportPromotionObjectStorage;
use Illuminate\Support\Facades\DB;

final readonly class CleanupImportPromotionObject
{
    public function __construct(private ImportPromotionObjectStorage $storage) {}

    public function handle(PromotionAttempt $attempt): bool
    {
        return DB::transaction(function () use ($attempt): bool {
            $locked = PromotionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status === PromotionAttemptStatus::Committed
                || ! $locked->status->isTerminal()
                || Document::query()->where('storage_key', $locked->reserved_object_key)->exists()) {
                return false;
            }
            $evidence = $locked->checksum_evidence;
            if (! is_array($evidence) || ! is_string($evidence['version_id'] ?? null)) {
                return false;
            }
            if ($locked->lease_expires_at?->isFuture() || $locked->lease_token_hash !== null) {
                throw ImportPromotionException::conflict('promotion_worker_not_quiescent');
            }
            $this->storage->deleteVersion($locked->reserved_object_key, $evidence['version_id']);

            return true;
        });
    }
}
