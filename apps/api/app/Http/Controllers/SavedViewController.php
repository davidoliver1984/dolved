<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SavedViewResource;
use App\Models\SavedView;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Support\Documents\NormaliseDocumentMetadataName;
use App\Support\Documents\RecordLibrarySettingsAudit;
use App\Support\Documents\SavedViewDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SavedViewController extends Controller
{
    public function __construct(private readonly RecordLibrarySettingsAudit $audit) {}

    public function index(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        [$user, $workspace] = $this->scope($request, $workspacePublicId, $workspaces);
        $views = SavedView::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->orderBy('normalised_name')
            ->get();

        return response()->json([
            'data' => SavedViewResource::collection($views)->resolve($request),
        ]);
    }

    public function store(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): SavedViewResource
    {
        [$user, $workspace] = $this->scope($request, $workspacePublicId, $workspaces);
        $values = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[^\p{C}]+$/u'],
            'definition' => ['required', 'array'],
        ]);
        $definition = SavedViewDefinition::forWrite($values['definition']);
        $this->rejectDuplicateName($workspace, $user, $values['name']);

        $view = DB::transaction(function () use ($workspace, $user, $values, $definition): SavedView {
            $view = new SavedView([
                'name' => trim($values['name']),
                'definition_schema_version' => 1,
                'definition' => $definition,
            ]);
            $view->workspace()->associate($workspace);
            $view->user()->associate($user);
            $view->save();
            $this->audit->handle(
                $workspace,
                $user,
                'saved_view',
                $view->public_id,
                'saved_view_created',
                [],
                ['name' => $view->name],
            );

            return $view;
        });

        return new SavedViewResource($view);
    }

    public function show(Request $request, string $workspacePublicId, string $savedViewPublicId, FindWorkspaceForUser $workspaces): SavedViewResource
    {
        [$user, $workspace] = $this->scope($request, $workspacePublicId, $workspaces);

        return new SavedViewResource($this->find($workspace, $user, $savedViewPublicId));
    }

    public function rename(Request $request, string $workspacePublicId, string $savedViewPublicId, FindWorkspaceForUser $workspaces): SavedViewResource
    {
        [$user, $workspace] = $this->scope($request, $workspacePublicId, $workspaces);
        $name = trim((string) $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[^\p{C}]+$/u'],
        ])['name']);

        $view = DB::transaction(function () use ($workspace, $user, $savedViewPublicId, $name): SavedView {
            $view = SavedView::query()
                ->whereKey($this->find($workspace, $user, $savedViewPublicId)->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->rejectDuplicateName($workspace, $user, $name, $view->id);
            $previous = $view->name;
            $view->name = $name;
            $view->save();
            $this->audit->handle(
                $workspace,
                $user,
                'saved_view',
                $view->public_id,
                'saved_view_renamed',
                ['name' => $previous],
                ['name' => $view->name],
            );

            return $view;
        });

        return new SavedViewResource($view);
    }

    public function destroy(Request $request, string $workspacePublicId, string $savedViewPublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        [$user, $workspace] = $this->scope($request, $workspacePublicId, $workspaces);
        DB::transaction(function () use ($workspace, $user, $savedViewPublicId): void {
            $view = $this->find($workspace, $user, $savedViewPublicId);
            $this->audit->handle(
                $workspace,
                $user,
                'saved_view',
                $view->public_id,
                'saved_view_deleted',
                ['name' => $view->name],
                [],
            );
            $view->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** @return array{User, Workspace} */
    private function scope(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): array
    {
        /** @var User $user */
        $user = $request->user();

        return [$user, $workspaces->handle($user, $workspacePublicId)->workspace];
    }

    private function find(Workspace $workspace, User $user, string $publicId): SavedView
    {
        return SavedView::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function rejectDuplicateName(Workspace $workspace, User $user, string $name, ?int $exceptId = null): void
    {
        $query = SavedView::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('normalised_name', NormaliseDocumentMetadataName::handle($name));
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'You already have a saved view with this name.']);
        }
    }
}
