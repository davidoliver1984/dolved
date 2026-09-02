<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\ArchiveDocumentCategory;
use App\Actions\Documents\ChangeDocumentFamilyOwner;
use App\Actions\Documents\CreateDocumentCategory;
use App\Actions\Documents\CreateDocumentTag;
use App\Actions\Documents\RenameDocumentCategory;
use App\Actions\Documents\RenameDocumentFamily;
use App\Actions\Documents\SyncDocumentFamilyTags;
use App\Actions\Documents\UpdateDocumentFamilyMetadata;
use App\Enums\DocumentCategoryStatus;
use App\Exceptions\DocumentFamilyOwnerChangeException;
use App\Http\Requests\ChangeDocumentFamilyOwnerRequest;
use App\Http\Requests\StoreDocumentTagRequest;
use App\Http\Requests\StoreDocumentTaxonomyRequest;
use App\Http\Requests\SyncDocumentFamilyTagsRequest;
use App\Http\Requests\UpdateDocumentFamilyMetadataRequest;
use App\Http\Resources\DocumentCategoryResource;
use App\Http\Resources\DocumentFamilyMetadataResource;
use App\Http\Resources\DocumentTagResource;
use App\Models\User;
use App\Queries\Documents\FindDocumentFamilyForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DocumentMetadataController extends Controller
{
    public function changeOwner(
        ChangeDocumentFamilyOwnerRequest $request,
        string $workspacePublicId,
        string $familyPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentFamilyForWorkspace $families,
        ChangeDocumentFamilyOwner $change,
    ): DocumentFamilyMetadataResource {
        /** @var User $actor */
        $actor = $request->user();
        $workspace = $workspaces->handle($actor, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);
        $family = $families->handle($workspace, $familyPublicId);
        $expectedOwner = User::query()->where('public_id', $request->string('expected_owner_public_id')->value())->firstOrFail();
        $intendedOwner = User::query()
            ->where('public_id', $request->string('intended_owner_public_id')->value())
            ->whereNull('disabled_at')
            ->whereHas('workspaceMemberships', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->firstOrFail();
        try {
            $result = $change->handle(
                $family,
                $actor,
                $intendedOwner,
                $request->integer('expected_owner_assignment_generation'),
                $expectedOwner->id,
                $request->string('idempotency_key')->value(),
            );
        } catch (DocumentFamilyOwnerChangeException $exception) {
            abort(in_array($exception->reason, [
                'owner_change_precondition_stale',
                'idempotency_key_conflict',
                'owner_change_command_incomplete',
            ], true) ? 409 : 404);
        }

        return new DocumentFamilyMetadataResource($result['family']->load(['category', 'owner', 'tags']));
    }

    public function index(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('viewDocumentMetadata', $workspace);

        return response()->json(['data' => [
            'categories' => DocumentCategoryResource::collection(
                $workspace->documentCategories()->orderBy('normalised_name')->get(),
            )->resolve($request),
            'tags' => DocumentTagResource::collection(
                $workspace->documentTags()->orderBy('normalised_name')->get(),
            )->resolve($request),
            'owners' => $workspace->memberships()
                ->with('user')->whereHas('user', fn ($query) => $query->whereNull('disabled_at'))
                ->orderBy('id')->get()->map(fn ($membership): array => [
                    'public_id' => $membership->user->public_id,
                    'name' => $membership->user->name,
                ])->values()->all(),
            'locations' => $workspace->organisationalLocations()
                ->orderBy('name')->get(['public_id', 'name'])
                ->map(fn ($location): array => ['public_id' => $location->public_id, 'name' => $location->name])
                ->values()->all(),
        ]]);
    }

    public function showFamily(Request $request, string $workspacePublicId, string $familyPublicId, FindWorkspaceForUser $workspaces, FindDocumentFamilyForWorkspace $families): DocumentFamilyMetadataResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('viewDocumentMetadata', $workspace);

        $family = $families->handle($workspace, $familyPublicId)->load(['category', 'owner', 'tags']);
        $canEdit = Gate::allows('manageDocumentMetadata', $workspace);
        $family->setAttribute('capabilities', ['edit' => $canEdit]);
        $family->setAttribute('edit_options', $canEdit ? [
            'categories' => $workspace->documentCategories()
                ->where('status', DocumentCategoryStatus::Active->value)
                ->orderBy('normalised_name')
                ->get(['public_id', 'name'])
                ->map(fn ($category): array => ['public_id' => $category->public_id, 'name' => $category->name])
                ->values()->all(),
            'tags' => $workspace->documentTags()
                ->orderBy('normalised_name')
                ->get(['public_id', 'name'])
                ->map(fn ($tag): array => ['public_id' => $tag->public_id, 'name' => $tag->name])
                ->values()->all(),
            'owners' => $workspace->memberships()
                ->with('user')
                ->whereHas('user', fn ($query) => $query->whereNull('disabled_at'))
                ->orderBy('id')
                ->get()
                ->map(fn ($membership): array => [
                    'public_id' => $membership->user->public_id,
                    'name' => $membership->user->name,
                ])->values()->all(),
        ] : null);

        return new DocumentFamilyMetadataResource($family);
    }

    public function updateFamily(
        UpdateDocumentFamilyMetadataRequest $request,
        string $workspacePublicId,
        string $familyPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentFamilyForWorkspace $families,
        RenameDocumentFamily $rename,
        UpdateDocumentFamilyMetadata $update,
    ): DocumentFamilyMetadataResource {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);
        $family = $families->handle($workspace, $familyPublicId);
        $categoryPublicId = $request->validated('category_public_id');
        $category = $categoryPublicId === null ? null : $workspace->documentCategories()
            ->where('public_id', $categoryPublicId)
            ->where('status', DocumentCategoryStatus::Active->value)
            ->firstOrFail();
        abort_unless($family->owner?->public_id === $request->validated('owner_public_id'), 409);

        $family = DB::transaction(function () use ($rename, $family, $user, $request, $update, $category) {
            $renamed = $rename->handle($family, $user, $request->string('name')->value());

            return $update->handle(
                $renamed,
                $user,
                $request->validated('description'),
                $category,
                $request->validated('review_due_date'),
            );
        });

        return new DocumentFamilyMetadataResource($family);
    }

    public function syncTags(
        SyncDocumentFamilyTagsRequest $request,
        string $workspacePublicId,
        string $familyPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentFamilyForWorkspace $families,
        SyncDocumentFamilyTags $sync,
    ): DocumentFamilyMetadataResource {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);

        $requestedTagIds = $request->validated('tag_public_ids');
        abort_unless(
            $workspace->documentTags()->whereIn('public_id', $requestedTagIds)->count() === count($requestedTagIds),
            404,
        );

        return new DocumentFamilyMetadataResource($sync->handle(
            $families->handle($workspace, $familyPublicId),
            $user,
            $requestedTagIds,
        ));
    }

    public function storeCategory(StoreDocumentTaxonomyRequest $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, CreateDocumentCategory $create): DocumentCategoryResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);

        return new DocumentCategoryResource($create->handle($workspace, $user, $request->string('name')->value()));
    }

    public function renameCategory(StoreDocumentTaxonomyRequest $request, string $workspacePublicId, string $categoryPublicId, FindWorkspaceForUser $workspaces, RenameDocumentCategory $rename): DocumentCategoryResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);
        $category = $workspace->documentCategories()->where('public_id', $categoryPublicId)->firstOrFail();

        return new DocumentCategoryResource($rename->handle($category, $user, $request->string('name')->value()));
    }

    public function archiveCategory(Request $request, string $workspacePublicId, string $categoryPublicId, FindWorkspaceForUser $workspaces, ArchiveDocumentCategory $archive): DocumentCategoryResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);
        $category = $workspace->documentCategories()->where('public_id', $categoryPublicId)->firstOrFail();

        return new DocumentCategoryResource($archive->handle($category, $user));
    }

    public function storeTag(StoreDocumentTagRequest $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, CreateDocumentTag $create): DocumentTagResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);

        return new DocumentTagResource($create->handle($workspace, $request->string('name')->value()));
    }
}
