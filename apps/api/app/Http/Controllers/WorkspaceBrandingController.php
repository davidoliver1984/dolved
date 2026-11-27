<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Services\Branding\WorkspaceBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceBrandingController extends Controller
{
    public function show(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, WorkspaceBranding $branding): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);

        return response()->json(['data' => $branding->effective($membership->workspace)]);
    }
}
