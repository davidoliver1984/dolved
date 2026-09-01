<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkAttemptStatus;
use App\Models\BulkOperationItemAttempt;
use Illuminate\Support\Facades\DB;

final readonly class ReclaimExpiredBulkAttempts
{
    public function __construct(private FinalizeBulkOperationAttempt $finalize) {}

    public function handle(int $limit = 50): int
    {
        $ids = BulkOperationItemAttempt::query()->where('status', BulkAttemptStatus::Open->value)
            ->where('lease_expires_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $attempt = DB::transaction(function () use ($id): ?BulkOperationItemAttempt {
                $attempt = BulkOperationItemAttempt::query()->lockForUpdate()->find($id);
                if (! $attempt instanceof BulkOperationItemAttempt || $attempt->status !== BulkAttemptStatus::Open
                    || $attempt->lease_expires_at->isFuture()) {
                    return null;
                }
                $attempt->forceFill([
                    'status' => BulkAttemptStatus::Abandoned,
                    'failure_category' => 'lease_expired',
                    'completed_at' => now(),
                ])->save();

                return $attempt;
            }, 3);
            if ($attempt !== null) {
                $this->finalize->handle($attempt);
                $count++;
            }
        }

        return $count;
    }
}
