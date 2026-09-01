<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BulkOperations\CancelBulkOperation;
use App\Actions\BulkOperations\ConfirmBulkOperation;
use App\Actions\BulkOperations\CreateBulkOperation;
use App\Enums\BulkOperationType;
use App\Enums\BulkSelectionMode;
use App\Exceptions\BulkOperationException;
use App\Http\Requests\CreateBulkOperationRequest;
use App\Jobs\ExecuteBulkOperation;
use App\Models\BulkOperation;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BulkOperationController extends Controller
{
    public function index(
        Request $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $page = BulkOperation::query()
            ->where('workspace_id', $workspace->id)
            ->withCount([
                'items as item_count',
                'items as eligible_count' => fn ($query) => $query->where('execution_status', 'eligible'),
                'items as excluded_count' => fn ($query) => $query->where('execution_status', 'excluded'),
                'items as open_attempt_count' => fn ($query) => $query->whereHas('attempts', fn ($attempts) => $attempts->where('status', 'open')),
                'items as waiting_on_subordinate_count' => fn ($query) => $query->where('execution_status', 'waiting_on_subordinate'),
                'items as succeeded_count' => fn ($query) => $query->where('execution_status', 'succeeded'),
                'items as skipped_count' => fn ($query) => $query->where('execution_status', 'skipped'),
                'items as failed_retryable_count' => fn ($query) => $query->where('execution_status', 'failed_retryable'),
                'items as failed_permanent_count' => fn ($query) => $query->where('execution_status', 'failed_permanent'),
                'items as cancelled_count' => fn ($query) => $query->where('execution_status', 'cancelled'),
            ])
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (BulkOperation $operation): array => $this->serializeSummary($operation))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

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

    public function confirm(
        Request $request,
        string $workspacePublicId,
        string $operationPublicId,
        FindWorkspaceForUser $workspaces,
        ConfirmBulkOperation $confirm,
    ): JsonResponse {
        [$user, $operation] = $this->authorisedOperation($request, $workspacePublicId, $operationPublicId, $workspaces);
        try {
            $operation = $confirm->handle($operation, $user);
        } catch (BulkOperationException $exception) {
            abort($exception->getCode(), $exception->getMessage());
        }

        return response()->json(['data' => $this->serialize($operation)]);
    }

    public function cancel(
        Request $request,
        string $workspacePublicId,
        string $operationPublicId,
        FindWorkspaceForUser $workspaces,
        CancelBulkOperation $cancel,
    ): JsonResponse {
        [$user, $operation] = $this->authorisedOperation($request, $workspacePublicId, $operationPublicId, $workspaces);
        try {
            $operation = $cancel->handle($operation, $user);
        } catch (BulkOperationException $exception) {
            abort($exception->getCode(), $exception->getMessage());
        }

        return response()->json(['data' => $this->serialize($operation)]);
    }

    public function retry(
        Request $request,
        string $workspacePublicId,
        string $operationPublicId,
        FindWorkspaceForUser $workspaces,
    ): JsonResponse {
        [, $operation] = $this->authorisedOperation($request, $workspacePublicId, $operationPublicId, $workspaces);
        ExecuteBulkOperation::dispatch($operation->id);

        return response()->json(['data' => $this->serialize($operation->refresh())], 202);
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
            'cancellation_requested_at' => $operation->cancellation_requested_at,
            'counts' => [
                'total' => $operation->items->count(),
                'eligible' => (int) ($counts['eligible'] ?? 0),
                'excluded' => (int) ($counts['excluded'] ?? 0),
                'open_attempts' => $operation->items()->whereHas('attempts', fn ($query) => $query->where('status', 'open'))->count(),
                'waiting_on_subordinate' => (int) ($counts['waiting_on_subordinate'] ?? 0),
                'succeeded' => (int) ($counts['succeeded'] ?? 0),
                'skipped' => (int) ($counts['skipped'] ?? 0),
                'failed_retryable' => (int) ($counts['failed_retryable'] ?? 0),
                'failed_permanent' => (int) ($counts['failed_permanent'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
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
                'terminal_reason' => $item->terminal_reason,
                'result_identity' => $item->result_identity,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSummary(BulkOperation $operation): array
    {
        return [
            'public_id' => $operation->public_id,
            'operation_type' => $operation->operation_type->value,
            'status' => $operation->status->value,
            'selection_mode' => $operation->selection_mode->value,
            'created_at' => $operation->created_at?->toIso8601String(),
            'confirmed_at' => $operation->confirmed_at?->toIso8601String(),
            'counts' => [
                'total' => (int) $operation->item_count,
                'eligible' => (int) $operation->eligible_count,
                'excluded' => (int) $operation->excluded_count,
                'open_attempts' => (int) $operation->open_attempt_count,
                'waiting_on_subordinate' => (int) $operation->waiting_on_subordinate_count,
                'succeeded' => (int) $operation->succeeded_count,
                'skipped' => (int) $operation->skipped_count,
                'failed_retryable' => (int) $operation->failed_retryable_count,
                'failed_permanent' => (int) $operation->failed_permanent_count,
                'cancelled' => (int) $operation->cancelled_count,
            ],
        ];
    }

    /** @return array{User, BulkOperation} */
    private function authorisedOperation(
        Request $request,
        string $workspacePublicId,
        string $operationPublicId,
        FindWorkspaceForUser $workspaces,
    ): array {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $operation = BulkOperation::query()->where('workspace_id', $workspace->id)
            ->where('public_id', $operationPublicId)->with('items')->firstOrFail();

        return [$user, $operation];
    }
}
