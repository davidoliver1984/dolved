<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Exceptions\BulkOperationException;
use App\Jobs\ExecuteBulkOperation;
use App\Models\BulkOperation;
use App\Models\User;
use App\Support\BulkOperations\RecordBulkOperationAudit;
use Illuminate\Support\Facades\DB;

final readonly class CancelBulkOperation
{
    public function __construct(
        private RecordBulkOperationAudit $audit,
        private ConvergeBulkOperationCancellation $converge,
    ) {}

    public function handle(BulkOperation $operation, User $actor): BulkOperation
    {
        $cancelled = DB::transaction(function () use ($operation, $actor): BulkOperation {
            $parent = BulkOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($parent->status->isTerminal()) {
                throw BulkOperationException::notCancellable();
            }
            if ($parent->cancellation_requested_at === null) {
                $parent->forceFill(['cancellation_requested_at' => now()])->save();
                $this->audit->record($parent, 'bulk_operation.cancellation_requested', 'browser', [
                    'operation_type' => $parent->operation_type->value,
                    'requested_by_user_id' => $actor->id,
                ]);
            }

            return $parent;
        }, 3);

        $cancelled = $this->converge->handle($cancelled);

        if (! $cancelled->status->isTerminal()) {
            ExecuteBulkOperation::dispatch($cancelled->id);
        }

        return $cancelled->refresh();
    }
}
