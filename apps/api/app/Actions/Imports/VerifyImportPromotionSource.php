<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\PromotionAttemptStatus;
use App\Exceptions\ImportPromotionException;
use App\Models\PromotionAttempt;
use App\Services\Documents\ImportPromotionObjectStorage;
use Illuminate\Support\Facades\DB;

final readonly class VerifyImportPromotionSource
{
    public function __construct(private ImportPromotionObjectStorage $storage) {}

    public function handle(PromotionAttempt $attempt, string $leaseToken, int $leaseGeneration): PromotionAttempt
    {
        $candidate = PromotionAttempt::query()->with(['item.workspace', 'item.batch'])->findOrFail($attempt->id);
        $this->assertLease($candidate, $leaseToken, $leaseGeneration, PromotionAttemptStatus::Copying);
        $evidence = $this->storage->materialise($candidate->item->workspace, $candidate->item, $candidate->reserved_object_key);

        return DB::transaction(function () use ($attempt, $leaseToken, $leaseGeneration, $evidence): PromotionAttempt {
            $locked = PromotionAttempt::query()->with('item.batch')->lockForUpdate()->findOrFail($attempt->id);
            $this->assertLease($locked, $leaseToken, $leaseGeneration, PromotionAttemptStatus::Copying);
            $locked->checksum_evidence = $evidence;
            $locked->status = PromotionAttemptStatus::SourceVerified;
            $locked->save();

            return $locked;
        });
    }

    private function assertLease(PromotionAttempt $attempt, string $token, int $generation, PromotionAttemptStatus $status): void
    {
        if ($attempt->status !== $status
            || $attempt->lease_generation !== $generation
            || $attempt->lease_token_hash === null
            || ! hash_equals($attempt->lease_token_hash, hash('sha256', $token))
            || $attempt->lease_expires_at?->isPast()
            || $attempt->cancellation_requested_at !== null
            || $attempt->item->batch->retention_expires_at->isPast()) {
            throw ImportPromotionException::conflict('stale_promotion_lease');
        }
    }
}
