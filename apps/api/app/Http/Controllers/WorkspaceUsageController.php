<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceUsageRequest;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Queries\Workspaces\GetWorkspaceUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WorkspaceUsageController extends Controller
{
    public function show(WorkspaceUsageRequest $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, GetWorkspaceUsage $usage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);
        Gate::authorize('viewUsage', $membership->workspace);

        return response()->json(['data' => $usage->handle($membership->workspace, (string) $request->validated('range', '30d'))]);
    }
}
