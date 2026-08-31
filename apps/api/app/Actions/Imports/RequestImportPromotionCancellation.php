<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\PromotionAttemptStatus;
use App\Exceptions\ImportPromotionException;
use App\Models\PromotionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestImportPromotionCancellation
{
    public function handle(PromotionAttempt $attempt, User $actor): PromotionAttempt
    {
        return DB::transaction(function () use ($attempt, $actor): PromotionAttempt {
            $locked = PromotionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! $actor->workspaceMemberships()->where('workspace_id', $locked->workspace_id)->exists()) {
                throw ImportPromotionException::conflict('authorization_changed');
            }
            if ($locked->status->isTerminal()) {
                return $locked;
            }
            $locked->cancellation_requested_at ??= now();
            if ($locked->status === PromotionAttemptStatus::Reserved) {
                $locked->status = PromotionAttemptStatus::Abandoned;
                $locked->terminal_reason = 'cancelled';
            }
            $locked->save();

            return $locked;
        });
    }
}
