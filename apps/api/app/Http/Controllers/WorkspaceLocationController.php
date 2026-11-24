<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workspaces\RetireOrganisationalLocation;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class WorkspaceLocationController extends Controller
{
    public function index(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);

        $locations = $workspace->organisationalLocations()
            ->with('parent')
            ->withCount([
                'children as active_children_count' => fn ($query) => $query->whereNull('retired_at'),
                'applicabilitySnapshots',
                'defaultForDocumentFamilies',
            ])
            ->orderByRaw('retired_at is not null')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $locations->map(fn (OrganisationalLocation $location): array => $this->payload($location))->all()]);
    }

    public function store(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'kind' => ['required', Rule::in(['site', 'service', 'region'])],
            'parent_public_id' => ['nullable', 'uuid'],
        ]);
        $location = DB::transaction(function () use ($validated, $workspace): OrganisationalLocation {
            $parentId = null;
            if ($validated['parent_public_id'] ?? null) {
                $parentId = $workspace->organisationalLocations()
                    ->whereNull('retired_at')
                    ->where('public_id', $validated['parent_public_id'])
                    ->lockForUpdate()
                    ->value('id');
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

            return $location;
        });

        return response()->json(['data' => $this->payload($location->load('parent')->loadCount([
            'children as active_children_count',
            'applicabilitySnapshots',
            'defaultForDocumentFamilies',
        ]))], 201);
    }

    public function retire(
        Request $request,
        string $workspacePublicId,
        string $locationPublicId,
        FindWorkspaceForUser $workspaces,
        RetireOrganisationalLocation $retire,
    ): JsonResponse {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $location = $workspace->organisationalLocations()->where('public_id', $locationPublicId)->firstOrFail();
        /** @var User $actor */
        $actor = $request->user();
        $location = $retire->handle($location, $actor, (string) Str::uuid())
            ->load('parent')->loadCount([
                'children as active_children_count' => fn ($query) => $query->whereNull('retired_at'),
                'applicabilitySnapshots',
                'defaultForDocumentFamilies',
            ]);

        return response()->json(['data' => $this->payload($location)]);
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
            'status' => $location->retired_at === null ? 'active' : 'retired',
            'usage' => [
                'active_children' => (int) ($location->active_children_count ?? 0),
                'applicability_snapshots' => (int) ($location->applicability_snapshots_count ?? 0),
                'family_defaults' => (int) ($location->default_for_document_families_count ?? 0),
            ],
            'capabilities' => [
                'retire' => $location->retired_at === null && (int) ($location->active_children_count ?? 0) === 0,
            ],
        ];
    }
}
