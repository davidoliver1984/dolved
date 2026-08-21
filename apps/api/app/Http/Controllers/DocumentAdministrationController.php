<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\RequestDocumentDeletion;
use App\Actions\Documents\RetryDocumentIngestion;
use App\Enums\DocumentStatus;
use App\Http\Requests\DeleteDocumentRequest;
use App\Http\Requests\ListDocumentsRequest;
use App\Http\Requests\RetryDocumentIngestionRequest;
use App\Http\Resources\DocumentAdministrationResource;
use App\Models\User;
use App\Queries\Documents\FindDocumentForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Support\CorrelationId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DocumentAdministrationController extends Controller
{
    public function index(
        ListDocumentsRequest $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $values = $request->validated();
        $query = $workspace->documents()
            ->with(['createdBy', 'latestIngestionAttempt', 'deletionOperation'])
            ->when($values['search'] ?? null, fn (Builder $query, string $search): Builder => $query->whereRaw(
                'LOWER(source_filename) LIKE ?',
                ['%'.mb_strtolower($search).'%'],
            ))
            ->when($values['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($values['governance_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('governance_status', $status))
            ->when($values['created_by_user_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('created_by_user_id', $id))
            ->when($values['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', $date))
            ->when($values['created_to'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<=', $date))
            ->when($values['failure_category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('failure_category', $category))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return DocumentAdministrationResource::collection($query->paginate((int) ($values['per_page'] ?? 25)));
    }

    public function show(
        ListDocumentsRequest $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
    ): DocumentAdministrationResource {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $document = $documents->handle($workspace, $documentPublicId);
        abort_if(
            in_array($document->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true),
            404,
        );
        Gate::authorize('view', $document);

        return new DocumentAdministrationResource($document->load(['createdBy', 'latestIngestionAttempt', 'deletionOperation']));
    }

    public function retry(
        RetryDocumentIngestionRequest $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
        RetryDocumentIngestion $retry,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $document = $documents->handle($workspace, $documentPublicId);
        Gate::authorize('retry', $document);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $operation = $retry->handle(
            $document,
            $user,
            $request->validated('idempotency_key'),
            $correlationId,
        );

        return response()->json(['data' => [
            'operation' => [
                'event_id' => $operation->event_id,
                'idempotency_key' => $operation->idempotency_key,
            ],
            'document' => (new DocumentAdministrationResource(
                $operation->document->load(['createdBy', 'latestIngestionAttempt', 'deletionOperation']),
            ))->resolve($request),
        ]], 202)->header('X-Correlation-ID', $correlationId);
    }

    public function destroy(
        DeleteDocumentRequest $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
        RequestDocumentDeletion $deletion,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $document = $documents->handle($workspace, $documentPublicId);
        Gate::authorize('delete', $document);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $operation = $deletion->handle($document, $user, $correlationId);

        return response()->json(['data' => [
            'operation' => [
                'public_id' => $operation->public_id,
                'status' => $operation->status->value,
            ],
        ]], 202)->header('X-Correlation-ID', $correlationId);
    }
}
