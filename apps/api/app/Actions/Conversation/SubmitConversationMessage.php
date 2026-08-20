<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\ConversationStatus;
use App\Enums\GenerationRunStatus;
use App\Enums\MessageRole;
use App\Exceptions\ConversationException;
use App\Jobs\ExecuteGenerationRun;
use App\Models\Conversation;
use App\Models\GenerationRun;
use App\Models\Message;
use App\Models\User;
use App\Support\Usage\RecordWorkspaceUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SubmitConversationMessage
{
    public function __construct(private RecordWorkspaceUsage $usage) {}

    public function handle(Conversation $conversation, User $user, string $text, string $idempotencyKey): GenerationRun
    {
        [$run, $created] = DB::transaction(function () use ($conversation, $user, $text, $idempotencyKey): array {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($locked->status !== ConversationStatus::Active) {
                throw new ConversationException('The conversation is not accepting messages.');
            }
            $existing = Message::query()
                ->where('conversation_id', $locked->id)
                ->where('submission_idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof Message) {
                if (! hash_equals($existing->display_text, $text)) {
                    throw new ConversationException('The message idempotency key was reused with different content.');
                }

                return [$existing->generationRuns()->oldest('id')->firstOrFail(), false];
            }
            if ($locked->generationRuns()->whereIn('status', $this->activeStatusValues())->exists()) {
                throw new ConversationException('The conversation already has an active generation run.');
            }
            $ordinal = ((int) $locked->messages()->max('ordinal')) + 1;
            $message = Message::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'conversation_id' => $locked->id,
                'ordinal' => $ordinal,
                'role' => MessageRole::User,
                'kind' => null,
                'display_text' => $text,
                'created_by_user_id' => $user->id,
                'in_reply_to_message_id' => null,
                'submission_idempotency_key' => $idempotencyKey,
            ]);
            $this->usage->activity($locked->workspace_id, 'user_submission', $message->public_id);
            if ($ordinal === 1) {
                $locked->update(['title' => $this->title($text)]);
            }

            return [GenerationRun::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'conversation_id' => $locked->id,
                'user_message_id' => $message->id,
                'assistant_message_id' => null,
                'retry_of_run_id' => null,
                'retry_idempotency_key' => null,
                'status' => GenerationRunStatus::Queued,
                'correlation_id' => (string) Str::uuid(),
            ]), true];
        });
        if ($created) {
            ExecuteGenerationRun::dispatch($run->id);
        }

        return $run;
    }

    /** @return list<string> */
    private function activeStatusValues(): array
    {
        return collect(GenerationRunStatus::cases())->reject->isTerminal()->map->value->all();
    }

    private function title(string $text): string
    {
        $maximum = (int) config('conversation.title_max_characters');
        $normalised = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        if (mb_strlen($normalised) <= $maximum) {
            return $normalised;
        }
        $bounded = mb_substr($normalised, 0, $maximum + 1);
        $space = mb_strrpos($bounded, ' ');

        return rtrim(mb_substr($bounded, 0, $space === false ? $maximum : $space));
    }
}
