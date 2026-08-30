<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\Documents\GetWorkspaceKnowledgeReadiness;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceKnowledgeReadinessController extends Controller
{
    public function show(
        Request $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        GetWorkspaceKnowledgeReadiness $readiness,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;

        return response()->json(['data' => [
            'searchable_document_count' => $readiness->count($workspace),
        ]]);
    }

    public function starterQuestions(
        Request $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        GetWorkspaceKnowledgeReadiness $readiness,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;

        return response()->json(['data' => $readiness->starterQuestions($workspace)]);
    }
}
