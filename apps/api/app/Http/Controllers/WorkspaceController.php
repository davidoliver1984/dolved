<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Queries\Workspaces\ListWorkspacesForUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceController extends Controller
{
    public function index(
        Request $request,
        ListWorkspacesForUser $workspaces,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();

        return WorkspaceResource::collection($workspaces->handle($user));
    }

    public function show(
        Request $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
    ): WorkspaceResource {
        /** @var User $user */
        $user = $request->user();

        return new WorkspaceResource(
            $workspaces->handle($user, $workspacePublicId),
        );
    }
}
