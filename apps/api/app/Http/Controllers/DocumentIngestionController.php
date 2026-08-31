<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\RequestLegacyDocumentIngestion;
use App\Http\Resources\DocumentResource;
use App\Models\User;
use App\Queries\Documents\FindDocumentForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentIngestionController extends Controller
{
    public function store(
        Request $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
        RequestLegacyDocumentIngestion $requestIngestion,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $document = $documents->handle($workspace, $documentPublicId);
        Gate::authorize('requestIngestion', $document);
        $correlationId = CorrelationId::resolve(
            $request->header('X-Correlation-ID'),
        );
        $resource = new DocumentResource(
            $requestIngestion->handle($document, $correlationId),
        );

        return $resource->response()
            ->setStatusCode(202)
            ->header('X-Correlation-ID', $correlationId);
    }
}
