<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\PromotionAttemptStatus;
use App\Exceptions\ImportPromotionException;
use App\Models\PromotionAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClaimImportPromotion
{
    /** @return array{attempt: PromotionAttempt, lease_token: string, lease_generation: int} */
    public function handle(PromotionAttempt $attempt): array
    {
        return DB::transaction(function () use ($attempt): array {
            $locked = PromotionAttempt::query()->with('item.batch')->lockForUpdate()->findOrFail($attempt->id);
            $now = now();
            $reclaimable = in_array($locked->status, [PromotionAttemptStatus::Copying, PromotionAttemptStatus::SourceVerified], true)
                && $locked->lease_expires_at?->lte($now);
            if (($locked->status !== PromotionAttemptStatus::Reserved && ! $reclaimable)
                || $locked->cancellation_requested_at !== null
                || $locked->item->batch->retention_expires_at->lte($now)) {
                throw ImportPromotionException::conflict('promotion_not_claimable');
            }
            $token = Str::random(64);
            $locked->status = $locked->status === PromotionAttemptStatus::Reserved
                ? PromotionAttemptStatus::Copying
                : $locked->status;
            $locked->lease_generation++;
            $locked->lease_token_hash = hash('sha256', $token);
            $locked->lease_expires_at = $now->copy()->addSeconds((int) config('imports.promotion.lease_seconds'));
            $locked->save();

            return ['attempt' => $locked, 'lease_token' => $token, 'lease_generation' => $locked->lease_generation];
        });
    }
}
