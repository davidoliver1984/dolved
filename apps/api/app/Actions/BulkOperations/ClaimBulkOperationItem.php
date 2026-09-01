<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkItemStatus;
use App\Enums\BulkOperationStatus;
use App\Models\BulkOperation;
use App\Models\BulkOperationItem;
use App\Models\BulkOperationItemAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClaimBulkOperationItem
{
    public function handle(BulkOperation $operation, string $executorIdentity): ?BulkOperationItemAttempt
    {
        BulkOperation::query()
            ->whereKey($operation->id)
            ->where('status', BulkOperationStatus::Queued->value)
            ->whereNull('cancellation_requested_at')
            ->update(['status' => BulkOperationStatus::Running->value]);

        return DB::transaction(function () use ($operation, $executorIdentity): ?BulkOperationItemAttempt {
            $parent = BulkOperation::query()->findOrFail($operation->id);
            if ($parent->cancellation_requested_at !== null || $parent->status->isTerminal()) {
                return null;
            }

            $query = BulkOperationItem::query()
                ->where('bulk_operation_id', $parent->id)
                ->whereIn('execution_status', [BulkItemStatus::Eligible->value, BulkItemStatus::FailedRetryable->value])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('bulk_operation_item_attempts as a')
                        ->whereColumn('a.bulk_operation_item_id', 'bulk_operation_items.id')
                        ->whereRaw('a.generation > COALESCE(bulk_operation_items.incorporated_attempt_generation, 0)');
                })
                ->orderBy('ordinal');
            $item = DB::getDriverName() === 'pgsql'
                ? $query->lock('FOR UPDATE SKIP LOCKED')->first()
                : $query->lockForUpdate()->first();
            if (! $item instanceof BulkOperationItem) {
                return null;
            }

            $generation = ((int) BulkOperationItemAttempt::query()
                ->where('bulk_operation_item_id', $item->id)->max('generation')) + 1;
            $now = now();

            return BulkOperationItemAttempt::query()->create([
                'bulk_operation_item_id' => $item->id,
                'workspace_id' => $item->workspace_id,
                'attempt_ordinal' => $generation,
                'generation' => $generation,
                'status' => 'open',
                'lease_expires_at' => $now->copy()->addSeconds((int) config('bulk_operations.attempt_lease_seconds')),
                'started_at' => $now,
                'executor_identity' => $executorIdentity,
                'invocation_idempotency_key' => (string) Str::uuid(),
                'attempt_token' => (string) Str::uuid(),
            ]);
        }, 3);
    }
}
