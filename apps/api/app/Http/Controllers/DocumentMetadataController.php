<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\ArchiveDocumentCategory;
use App\Actions\Documents\CreateDocumentCategory;
use App\Actions\Documents\CreateDocumentTag;
use App\Actions\Documents\RenameDocumentFamily;
use App\Actions\Documents\SyncDocumentFamilyTags;
use App\Actions\Documents\UpdateDocumentFamilyMetadata;
use App\Enums\DocumentCategoryStatus;
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
        ]]);
    }

    public function showFamily(Request $request, string $workspacePublicId, string $familyPublicId, FindWorkspaceForUser $workspaces, FindDocumentFamilyForWorkspace $families): DocumentFamilyMetadataResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('viewDocumentMetadata', $workspace);

        return new DocumentFamilyMetadataResource(
            $families->handle($workspace, $familyPublicId)->load(['category', 'owner', 'tags']),
        );
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
        $owner = User::query()
            ->where('public_id', $request->validated('owner_public_id'))
            ->whereNull('disabled_at')
            ->whereHas('workspaceMemberships', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->firstOrFail();

        $family = DB::transaction(function () use ($rename, $family, $user, $request, $update, $category, $owner) {
            $renamed = $rename->handle($family, $user, $request->string('name')->value());

            return $update->handle(
                $renamed,
                $user,
                $request->validated('description'),
                $category,
                $owner,
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

        return new DocumentCategoryResource($create->handle($workspace, $request->string('name')->value()));
    }

    public function archiveCategory(Request $request, string $workspacePublicId, string $categoryPublicId, FindWorkspaceForUser $workspaces, ArchiveDocumentCategory $archive): DocumentCategoryResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentMetadata', $workspace);
        $category = $workspace->documentCategories()->where('public_id', $categoryPublicId)->firstOrFail();

        return new DocumentCategoryResource($archive->handle($category));
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
