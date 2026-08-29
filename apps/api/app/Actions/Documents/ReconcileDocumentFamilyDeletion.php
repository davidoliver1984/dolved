<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentFamilyDeletionStatus;
use App\Models\DocumentFamilyDeletionOperation;
use Illuminate\Support\Facades\DB;

final class ReconcileDocumentFamilyDeletion
{
    public function handle(int $operationId): DocumentFamilyDeletionOperation
    {
        return DB::transaction(function () use ($operationId): DocumentFamilyDeletionOperation {
            $operation = DocumentFamilyDeletionOperation::query()->with(['family', 'children'])->whereKey($operationId)->lockForUpdate()->firstOrFail();
            if ($operation->status === DocumentFamilyDeletionStatus::Completed) {
                return $operation;
            }
            $children = $operation->children;
            $status = match (true) {
                $children->contains(fn ($child): bool => $child->status === DocumentDeletionStatus::Failed) => DocumentFamilyDeletionStatus::PartiallyFailed,
                $children->count() === $operation->child_count
                    && $children->every(fn ($child): bool => $child->status === DocumentDeletionStatus::Completed) => DocumentFamilyDeletionStatus::Completed,
                default => DocumentFamilyDeletionStatus::Processing,
            };
            $operation->forceFill([
                'status' => $status,
                'completed_at' => $status === DocumentFamilyDeletionStatus::Completed ? now() : null,
            ])->save();
            if ($status === DocumentFamilyDeletionStatus::Completed && $operation->family->tombstoned_at === null) {
                $operation->family->forceFill(['tombstoned_at' => now()])->save();
            }

            return $operation->refresh();
        }, attempts: 3);
    }
}
