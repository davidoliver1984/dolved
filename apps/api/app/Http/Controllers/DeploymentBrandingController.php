<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceInvitationStatus;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Branding\WorkspaceBranding;
use Illuminate\Http\JsonResponse;

final class DeploymentBrandingController extends Controller
{
    public function show(WorkspaceBranding $branding): JsonResponse
    {
        $publicId = config('branding.workspace_public_id');
        $workspace = is_string($publicId) && $publicId !== ''
            ? Workspace::query()->where('public_id', $publicId)->first()
            : null;

        return response()->json(['data' => $workspace === null
            ? ['mode' => 'default', 'revision' => null, 'configuration' => $branding->defaults()]
            : $branding->effective($workspace)]);
    }

    public function invitation(string $token, WorkspaceBranding $branding): JsonResponse
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token_digest', hash('sha256', $token))
            ->where('status', WorkspaceInvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return response()->json(['data' => $branding->effective($invitation->workspace)]);
    }
}
