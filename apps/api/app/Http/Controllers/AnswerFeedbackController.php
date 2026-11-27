<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AnswerFeedback;
use App\Models\Conversation;
use App\Models\GenerationRun;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AnswerFeedbackController extends Controller
{
    private const RATINGS = ['helpful', 'not_helpful'];

    private const REASONS = ['inaccurate', 'incomplete', 'unclear', 'outdated', 'other'];

    public function update(
        Request $request,
        string $workspacePublicId,
        string $conversationPublicId,
        string $runPublicId,
        FindWorkspaceForUser $workspaces,
    ): JsonResponse {
        [$conversation, $run] = $this->conversationAndRun($request, $workspaces, $workspacePublicId, $conversationPublicId, $runPublicId);
        $validated = $request->validate([
            'rating' => ['required', Rule::in(self::RATINGS)],
            'reason_code' => ['nullable', Rule::in(self::REASONS)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_if($run->assistant_message_id === null || $run->completed_at === null, 409, 'Feedback is available after the answer has been completed.');
        /** @var User $actor */
        $actor = $request->user();
        $rating = (string) $validated['rating'];
        $reason = $rating === 'not_helpful' ? ($validated['reason_code'] ?? null) : null;
        $comment = $rating === 'not_helpful' ? trim((string) ($validated['comment'] ?? '')) : '';

        $feedback = DB::transaction(function () use ($conversation, $run, $actor, $rating, $reason, $comment): AnswerFeedback {
            $feedback = AnswerFeedback::query()->lockForUpdate()->firstOrNew(['generation_run_id' => $run->id]);
            $eventType = $feedback->exists ? 'feedback_updated' : 'feedback_created';
            $previous = $feedback->exists ? ['rating' => $feedback->rating, 'reason_code' => $feedback->reason_code] : null;
            if (! $feedback->exists) {
                $feedback->fill([
                    'public_id' => (string) Str::uuid(),
                    'workspace_id' => $conversation->workspace_id,
                    'assistant_message_id' => $run->assistant_message_id,
                    'submitted_by_user_id' => $actor->id,
                ]);
            }
            $feedback->fill([
                'rating' => $rating,
                'reason_code' => $reason,
                'comment' => $comment !== '' ? $comment : null,
                'submitted_at' => now(),
            ])->save();
            $this->audit($feedback, $run, $actor, $eventType, [
                'previous' => $previous,
                'current' => ['rating' => $rating, 'reason_code' => $reason],
                'has_comment' => $comment !== '',
            ]);

            return $feedback;
        });

        return response()->json(['data' => $this->payload($feedback)]);
    }

    public function destroy(
        Request $request,
        string $workspacePublicId,
        string $conversationPublicId,
        string $runPublicId,
        FindWorkspaceForUser $workspaces,
    ): JsonResponse {
        [, $run] = $this->conversationAndRun($request, $workspaces, $workspacePublicId, $conversationPublicId, $runPublicId);
        /** @var User $actor */
        $actor = $request->user();

        DB::transaction(function () use ($run, $actor): void {
            $feedback = AnswerFeedback::query()->where('generation_run_id', $run->id)->lockForUpdate()->firstOrFail();
            $this->audit($feedback, $run, $actor, 'feedback_removed', [
                'previous' => ['rating' => $feedback->rating, 'reason_code' => $feedback->reason_code],
                'has_comment' => $feedback->comment !== null,
            ]);
            $feedback->delete();
        });

        return response()->json(status: 204);
    }

    /** @return array{Conversation, GenerationRun} */
    private function conversationAndRun(Request $request, FindWorkspaceForUser $workspaces, string $workspacePublicId, string $conversationPublicId, string $runPublicId): array
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $conversation = Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_by_user_id', $user->id)
            ->where('public_id', $conversationPublicId)
            ->firstOrFail();
        $run = $conversation->generationRuns()->where('public_id', $runPublicId)->firstOrFail();

        return [$conversation, $run];
    }

    private function audit(AnswerFeedback $feedback, GenerationRun $run, User $actor, string $eventType, array $metadata): void
    {
        DB::table('answer_feedback_audit_events')->insert([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $run->workspace_id,
            'generation_run_id' => $run->id,
            'answer_feedback_id' => $feedback->id,
            'actor_user_id' => $actor->id,
            'event_type' => $eventType,
            'safe_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function payload(AnswerFeedback $feedback): array
    {
        return [
            'rating' => $feedback->rating,
            'reason_code' => $feedback->reason_code,
            'comment' => $feedback->comment,
            'submitted_at' => $feedback->submitted_at?->toIso8601String(),
        ];
    }
}
