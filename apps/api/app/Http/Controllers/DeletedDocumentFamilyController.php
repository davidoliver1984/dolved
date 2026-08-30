<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentFamilyDeletionOperation;
use App\Models\DocumentGovernanceAuditEvent;
use App\Models\User;
use App\Queries\Documents\PaginateDeletedDocumentFamilies;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DeletedDocumentFamilyController extends Controller
{
    public function __invoke(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, PaginateDeletedDocumentFamilies $deleted): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $page = $deleted->handle($workspace);

        return response()->json([
            'data' => collect($page->items())->map(fn (DocumentFamilyDeletionOperation $operation): array => $this->item($operation))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function item(DocumentFamilyDeletionOperation $operation): array
    {
        /** @var DocumentGovernanceAuditEvent|null $audit */
        $audit = $operation->family->governanceAuditEvents->first(
            fn (DocumentGovernanceAuditEvent $event): bool => ($event->new_values['operation_public_id'] ?? null) === $operation->public_id,
        );

        return [
            'family' => [
                'public_id' => $operation->family->public_id,
                'name' => $operation->family->name,
            ],
            'operation_public_id' => $operation->public_id,
            'deleted_at' => $operation->completed_at?->toIso8601String() ?? $operation->family->tombstoned_at?->toIso8601String(),
            'reason' => $audit?->reason,
            'audit_reference' => $audit?->public_id,
            'requested_by' => $operation->requestedBy === null ? null : [
                'public_id' => $operation->requestedBy->public_id,
                'name' => $operation->requestedBy->name,
            ],
            'versions_removed' => $operation->child_count,
        ];
    }
}
