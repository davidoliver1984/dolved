<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\ConfirmDocumentFamilyDeletion;
use App\Actions\Documents\PreviewDocumentFamilyDeletion;
use App\Exceptions\DocumentGovernanceException;
use App\Http\Requests\ConfirmDocumentFamilyDeletionRequest;
use App\Models\User;
use App\Queries\Documents\FindDocumentFamilyForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DocumentFamilyDeletionController extends Controller
{
    public function preview(
        Request $request,
        string $workspacePublicId,
        string $familyPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentFamilyForWorkspace $families,
        PreviewDocumentFamilyDeletion $preview,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $family = $families->handle($workspace, $familyPublicId);

        try {
            return response()->json(['data' => $preview->handle($family, $user)]);
        } catch (DocumentGovernanceException) {
            return response()->json(['error' => ['code' => 'family_deletion_conflict']], 409);
        }
    }

    public function confirm(
        ConfirmDocumentFamilyDeletionRequest $request,
        string $workspacePublicId,
        string $familyPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentFamilyForWorkspace $families,
        ConfirmDocumentFamilyDeletion $confirm,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $family = $families->handle($workspace, $familyPublicId);

        try {
            $operation = $confirm->handle(
                $family,
                $user,
                $request->string('confirmation_digest')->value(),
                $request->string('idempotency_key')->value(),
            );
        } catch (DocumentGovernanceException) {
            return response()->json(['error' => ['code' => 'family_deletion_conflict']], 409);
        }

        return response()->json(['data' => [
            'operation' => [
                'public_id' => $operation->public_id,
                'status' => $operation->status->value,
                'child_count' => $operation->child_count,
                'confirmation_state_digest' => $operation->confirmation_state_digest,
            ],
        ]], 202);
    }
}
