<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkOperationStatus;
use App\Exceptions\BulkOperationException;
use App\Jobs\ExecuteBulkOperation;
use App\Models\BulkOperation;
use App\Models\User;
use App\Support\BulkOperations\RecordBulkOperationAudit;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmBulkOperation
{
    public function __construct(private RecordBulkOperationAudit $audit) {}

    public function handle(BulkOperation $operation, User $actor): BulkOperation
    {
        $confirmed = DB::transaction(function () use ($operation, $actor): BulkOperation {
            $locked = BulkOperation::query()->with('items')->lockForUpdate()->findOrFail($operation->id);
            if ($locked->actor_user_id !== $actor->id) {
                throw BulkOperationException::confirmationActorMismatch();
            }
            if ($locked->status !== BulkOperationStatus::AwaitingConfirmation) {
                if (in_array($locked->status, [BulkOperationStatus::Queued, BulkOperationStatus::Running], true)) {
                    return $locked;
                }
                throw BulkOperationException::notConfirmable();
            }

            $locked->forceFill([
                'status' => BulkOperationStatus::Queued,
                'confirmed_at' => now(),
            ])->save();
            $this->audit->record($locked, 'bulk_operation.confirmed', 'browser', [
                'total_count' => $locked->items->count(),
                'eligible_count' => $locked->items->where('eligibility_status', 'eligible')->count(),
                'excluded_count' => $locked->items->where('eligibility_status', 'excluded')->count(),
                'membership_digest' => $locked->membership_digest,
                'operation_type' => $locked->operation_type->value,
            ]);

            return $locked;
        }, 3);

        ExecuteBulkOperation::dispatch($confirmed->id);

        return $confirmed->refresh();
    }
}
