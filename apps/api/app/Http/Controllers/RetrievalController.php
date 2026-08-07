<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Retrieval\RetrieveWorkspaceEvidence;
use App\Http\Requests\RetrieveWorkspaceEvidenceRequest;
use App\Models\User;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use Illuminate\Http\JsonResponse;

class RetrievalController extends Controller
{
    public function store(
        RetrieveWorkspaceEvidenceRequest $request,
        string $workspacePublicId,
        BuildAuthorisedKnowledgeScope $authorisation,
        RetrieveWorkspaceEvidence $retrieve,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $scope = $authorisation->handle($user, $workspacePublicId);
        $result = $retrieve->handle(
            $scope,
            $request->string('question')->value(),
            $request->integer('candidate_k', (int) config('retrieval.candidate_k')),
        );

        return response()->json(['data' => $result->toArray()]);
    }
}
