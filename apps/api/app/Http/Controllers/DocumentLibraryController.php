<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListDocumentFamiliesRequest;
use App\Http\Resources\DocumentFamilyLibraryResource;
use App\Models\User;
use App\Queries\Documents\PaginateDocumentLibrary;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DocumentLibraryController extends Controller
{
    public function index(
        ListDocumentFamiliesRequest $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        PaginateDocumentLibrary $library,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;

        return DocumentFamilyLibraryResource::collection($library->handle($workspace, $request->validated()));
    }
}
