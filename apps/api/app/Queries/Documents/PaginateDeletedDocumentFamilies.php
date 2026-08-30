<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentFamilyDeletionStatus;
use App\Models\DocumentFamilyDeletionOperation;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaginateDeletedDocumentFamilies
{
    /** @return LengthAwarePaginator<int, DocumentFamilyDeletionOperation> */
    public function handle(Workspace $workspace): LengthAwarePaginator
    {
        return DocumentFamilyDeletionOperation::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', DocumentFamilyDeletionStatus::Completed->value)
            ->whereHas('family', fn ($query) => $query->whereNotNull('tombstoned_at'))
            ->with([
                'family.governanceAuditEvents' => fn ($query) => $query
                    ->where('action', 'family_deletion_confirmed')
                    ->latest('occurred_at'),
                'requestedBy:id,public_id,name',
            ])
            ->latest('completed_at')
            ->latest('id')
            ->paginate(25);
    }
}
