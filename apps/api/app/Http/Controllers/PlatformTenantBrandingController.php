<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkspaceBrandingDraftRequest;
use App\Models\Workspace;
use App\Services\Branding\WorkspaceBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformTenantBrandingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Workspace::query()
            ->with('brandingProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (Workspace $workspace): array => [
                'public_id' => $workspace->public_id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'branding_mode' => $workspace->brandingProfile?->mode ?? 'default',
            ])]);
    }

    public function show(string $workspacePublicId, WorkspaceBranding $branding): JsonResponse
    {
        return response()->json(['data' => $branding->adminView($this->workspace($workspacePublicId))]);
    }

    public function store(
        StoreWorkspaceBrandingDraftRequest $request,
        string $workspacePublicId,
        WorkspaceBranding $branding,
    ): JsonResponse {
        return response()->json(['data' => $branding->saveDraft(
            $this->workspace($workspacePublicId),
            $request->user(),
            (int) $request->validated('draft_version'),
            $request->validated('configuration'),
        )]);
    }

    public function apply(Request $request, string $workspacePublicId, WorkspaceBranding $branding): JsonResponse
    {
        $validated = $request->validate(['draft_version' => ['required', 'integer', 'min:0']]);

        return response()->json(['data' => $branding->apply(
            $this->workspace($workspacePublicId),
            $request->user(),
            (int) $validated['draft_version'],
        )]);
    }

    public function mode(Request $request, string $workspacePublicId, WorkspaceBranding $branding): JsonResponse
    {
        $validated = $request->validate(['mode' => ['required', 'in:default,custom']]);

        return response()->json(['data' => $branding->switchMode(
            $this->workspace($workspacePublicId),
            $request->user(),
            $validated['mode'],
        )]);
    }

    private function workspace(string $publicId): Workspace
    {
        return Workspace::query()->where('public_id', $publicId)->firstOrFail();
    }
}
