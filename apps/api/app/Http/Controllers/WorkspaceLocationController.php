<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class WorkspaceLocationController extends Controller
{
    public function index(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);

        return response()->json(['data' => $workspace->organisationalLocations()->with('parent')->orderBy('name')->get()->map(fn (OrganisationalLocation $location): array => $this->payload($location))->all()]);
    }

    public function store(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'kind' => ['required', Rule::in(['site', 'service', 'region'])],
            'parent_public_id' => ['nullable', 'uuid'],
        ]);
        $parentId = null;
        if ($validated['parent_public_id'] ?? null) {
            $parentId = $workspace->organisationalLocations()->where('public_id', $validated['parent_public_id'])->value('id');
            abort_if($parentId === null, 422, 'The parent location is unavailable.');
        }
        $location = new OrganisationalLocation;
        $location->forceFill([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'parent_id' => $parentId,
            'name' => trim($validated['name']),
            'kind' => $validated['kind'],
        ])->save();

        return response()->json(['data' => $this->payload($location->load('parent'))], 201);
    }

    private function workspace(Request $request, string $publicId, FindWorkspaceForUser $workspaces): Workspace
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $publicId)->workspace;
        Gate::authorize('viewAdministration', $workspace);

        return $workspace;
    }

    private function payload(OrganisationalLocation $location): array
    {
        return [
            'public_id' => $location->public_id,
            'name' => $location->name,
            'kind' => $location->kind,
            'parent' => $location->parent ? ['public_id' => $location->parent->public_id, 'name' => $location->parent->name] : null,
        ];
    }
}
