<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Conversation\CancelGenerationRun;
use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\RetryGenerationRun;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Enums\ConversationStatus;
use App\Exceptions\ConversationException;
use App\Http\Requests\RetryGenerationRunRequest;
use App\Http\Requests\StoreConversationMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\GenerationRun;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $conversations = Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [ConversationStatus::Active->value, ConversationStatus::Archived->value])
            ->latest('updated_at')
            ->get();

        return ConversationResource::collection($conversations);
    }

    public function store(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, CreateConversation $create): ConversationResource
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;

        return new ConversationResource($create->handle($workspace, $user));
    }

    public function show(Request $request, string $workspacePublicId, string $conversationPublicId, FindWorkspaceForUser $workspaces): ConversationResource
    {
        $conversation = $this->conversation($request, $workspaces, $workspacePublicId, $conversationPublicId);

        return new ConversationResource($this->loadConversation($conversation));
    }

    public function message(StoreConversationMessageRequest $request, string $workspacePublicId, string $conversationPublicId, FindWorkspaceForUser $workspaces, SubmitConversationMessage $submit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->conversation($request, $workspaces, $workspacePublicId, $conversationPublicId);
        try {
            $run = $submit->handle($conversation, $user, trim($request->string('message')->value()), $request->string('idempotency_key')->value());
        } catch (ConversationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['run_id' => $run->public_id, 'status' => $run->status->value]], 202);
    }

    public function retry(RetryGenerationRunRequest $request, string $workspacePublicId, string $conversationPublicId, string $runPublicId, FindWorkspaceForUser $workspaces, RetryGenerationRun $retry): JsonResponse
    {
        $conversation = $this->conversation($request, $workspaces, $workspacePublicId, $conversationPublicId);
        try {
            $created = $retry->handle($this->run($conversation, $runPublicId), $request->string('idempotency_key')->value());
        } catch (ConversationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['run_id' => $created->public_id, 'status' => $created->status->value]], 202);
    }

    public function cancel(Request $request, string $workspacePublicId, string $conversationPublicId, string $runPublicId, FindWorkspaceForUser $workspaces, CancelGenerationRun $cancel): JsonResponse
    {
        $conversation = $this->conversation($request, $workspaces, $workspacePublicId, $conversationPublicId);
        try {
            $cancelled = $cancel->handle($this->run($conversation, $runPublicId));
        } catch (ConversationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['run_id' => $cancelled->public_id, 'status' => $cancelled->status->value]], 202);
    }

    private function conversation(Request $request, FindWorkspaceForUser $workspaces, string $workspacePublicId, string $conversationPublicId): Conversation
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;

        return Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $conversationPublicId)
            ->whereNotIn('status', [ConversationStatus::Deleting->value, ConversationStatus::Deleted->value])
            ->firstOrFail();
    }

    private function run(Conversation $conversation, string $runPublicId): GenerationRun
    {
        return $conversation->generationRuns()->where('public_id', $runPublicId)->firstOrFail();
    }

    private function loadConversation(Conversation $conversation): Conversation
    {
        return $conversation->load([
            'messages.inReplyTo',
            'generationRuns.userMessage',
            'generationRuns.assistantMessage',
            'generationRuns.retryOf',
            'generationRuns.deliveryEvents',
            'generationRuns.generatedAnswer.answerParts.evidenceSnapshots.document',
            'workspace',
        ]);
    }
}
