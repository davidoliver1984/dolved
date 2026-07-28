<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\CompleteDocumentUpload;
use App\Actions\Documents\InitializeDocumentUpload;
use App\Http\Requests\InitializeDocumentUploadRequest;
use App\Http\Resources\DocumentResource;
use App\Models\User;
use App\Queries\Documents\FindDocumentForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentUploadController extends Controller
{
    public function configuration(
        Request $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('uploadDocuments', $workspace);

        return response()->json([
            'data' => [
                'formats' => config('documents.formats'),
                'max_upload_bytes' => config('documents.max_upload_bytes'),
                'upload_concurrency' => config('documents.upload_concurrency'),
            ],
        ]);
    }

    public function store(
        InitializeDocumentUploadRequest $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        InitializeDocumentUpload $initialize,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('uploadDocuments', $workspace);

        $result = $initialize->handle(
            $workspace,
            $user,
            $request->string('filename')->value(),
            $request->string('media_type')->value(),
            $request->integer('size_bytes'),
            $request->extension(),
        );

        return response()->json([
            'data' => [
                'document' => new DocumentResource($result['document']),
                'upload' => $result['upload'],
            ],
        ], 201);
    }

    public function complete(
        Request $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
        CompleteDocumentUpload $complete,
    ): DocumentResource {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $document = $documents->handle($workspace, $documentPublicId);
        Gate::authorize('completeUpload', $document);

        return new DocumentResource($complete->handle($document));
    }
}
