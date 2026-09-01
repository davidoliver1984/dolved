<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BulkOperations\CreateBulkOperation;
use App\Enums\BulkOperationType;
use App\Enums\BulkSelectionMode;
use App\Exceptions\BulkOperationException;
use App\Http\Requests\CreateBulkOperationRequest;
use App\Models\BulkOperation;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BulkOperationController extends Controller
{
    public function store(
        CreateBulkOperationRequest $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        CreateBulkOperation $create,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $validated = $request->validated();

        try {
            $operation = $create->handle(
                $workspace,
                $user,
                BulkOperationType::from($validated['operation_type']),
                BulkSelectionMode::from($validated['selection_mode']),
                $validated['payload'],
                $validated['filters'],
                $validated['target_public_ids'],
                $validated['idempotency_key'],
            );
        } catch (BulkOperationException $exception) {
            abort($exception->getCode(), $exception->getMessage());
        }

        return response()->json(['data' => $this->serialize($operation)], 201);
    }

    public function show(
        Request $request,
        string $workspacePublicId,
        string $operationPublicId,
        FindWorkspaceForUser $workspaces,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $operation = BulkOperation::query()->where('workspace_id', $workspace->id)
            ->where('public_id', $operationPublicId)->with('items')->firstOrFail();

        return response()->json(['data' => $this->serialize($operation)]);
    }

    /** @return array<string, mixed> */
    private function serialize(BulkOperation $operation): array
    {
        $operation->loadMissing('items');
        $counts = $operation->items->countBy(fn ($item): string => $item->execution_status->value);

        return [
            'public_id' => $operation->public_id,
            'operation_type' => $operation->operation_type->value,
            'status' => $operation->status->value,
            'selection_mode' => $operation->selection_mode->value,
            'payload_schema_version' => $operation->payload_schema_version,
            'payload' => json_decode($operation->canonical_payload, true, flags: JSON_THROW_ON_ERROR),
            'filters' => json_decode($operation->filter_explanation, true, flags: JSON_THROW_ON_ERROR),
            'membership_digest' => $operation->membership_digest,
            'confirmed_at' => $operation->confirmed_at,
            'counts' => [
                'total' => $operation->items->count(),
                'eligible' => (int) ($counts['eligible'] ?? 0),
                'excluded' => (int) ($counts['excluded'] ?? 0),
            ],
            'exclusions' => $operation->items->whereNotNull('exclusion_reason')
                ->groupBy('exclusion_reason')->map->count()->sortKeys(),
            'items' => $operation->items->map(fn ($item): array => [
                'ordinal' => $item->ordinal,
                'target_kind' => $item->target_kind->value,
                'target_public_id' => $item->target_public_id,
                'target_display_label' => $item->target_display_label,
                'eligibility_status' => $item->eligibility_status->value,
                'exclusion_reason' => $item->exclusion_reason,
                'execution_status' => $item->execution_status->value,
            ])->values(),
        ];
    }
}
