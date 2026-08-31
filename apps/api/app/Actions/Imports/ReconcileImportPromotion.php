<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\PromotionAttemptStatus;
use App\Models\PromotionAttempt;
use Illuminate\Support\Facades\DB;

final class ReconcileImportPromotion
{
    public function handle(PromotionAttempt $attempt): PromotionAttempt
    {
        return DB::transaction(function () use ($attempt): PromotionAttempt {
            $locked = PromotionAttempt::query()->with('item.batch')->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status->isTerminal()) {
                return $locked;
            }
            $now = now();
            $leaseValid = $locked->lease_expires_at?->gt($now) === true && $locked->lease_token_hash !== null;
            if ($locked->item->batch->retention_expires_at->lte($now) && ! $leaseValid) {
                $locked->status = PromotionAttemptStatus::Expired;
                $locked->terminal_reason = 'retention_expired';
            } elseif ($locked->cancellation_requested_at !== null && ! $leaseValid) {
                $locked->status = PromotionAttemptStatus::Abandoned;
                $locked->terminal_reason = 'cancelled';
            } elseif ($locked->failures()->count() >= (int) config('imports.promotion.failure_ceiling') && ! $leaseValid) {
                $locked->status = PromotionAttemptStatus::Failed;
                $locked->terminal_reason = 'technical_exhaustion';
            }
            if ($locked->status->isTerminal()) {
                $locked->lease_token_hash = null;
                $locked->lease_expires_at = null;
            }
            $locked->save();

            return $locked;
        });
    }
}
