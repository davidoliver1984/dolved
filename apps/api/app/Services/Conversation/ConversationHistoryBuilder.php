<?php

declare(strict_types=1);

namespace App\Services\Conversation;

use App\Enums\MessageRole;
use App\Models\Message;

final readonly class ConversationHistoryBuilder
{
    /** @return list<array{user_message: string, assistant_message: string, assistant_kind: string, user_ordinal: int, assistant_ordinal: int}> */
    public function forMessage(Message $current): array
    {
        $messages = Message::query()
            ->where('workspace_id', $current->workspace_id)
            ->where('conversation_id', $current->conversation_id)
            ->where('ordinal', '<', $current->ordinal)
            ->orderBy('ordinal')
            ->get();
        $byId = $messages->keyBy('id');
        $turns = [];
        foreach ($messages as $assistant) {
            if ($assistant->role !== MessageRole::Assistant || $assistant->in_reply_to_message_id === null) {
                continue;
            }
            $user = $byId->get($assistant->in_reply_to_message_id);
            if (! $user instanceof Message || $user->role !== MessageRole::User) {
                continue;
            }
            $turns[] = [
                'user_message' => $user->display_text,
                'assistant_message' => $assistant->display_text,
                'assistant_kind' => $assistant->kind->value,
                'user_ordinal' => $user->ordinal,
                'assistant_ordinal' => $assistant->ordinal,
            ];
        }
        $turns = array_slice($turns, -max(0, (int) config('conversation.context_turn_limit')));
        $maximum = (int) config('conversation.context_max_characters');
        while ($turns !== [] && $this->characters($turns, $current->display_text) > $maximum) {
            array_shift($turns);
        }

        return array_values($turns);
    }

    /** @param list<array{user_message: string, assistant_message: string, assistant_kind: string, user_ordinal: int, assistant_ordinal: int}> $turns */
    private function characters(array $turns, string $current): int
    {
        return mb_strlen($current) + array_sum(array_map(
            fn (array $turn): int => mb_strlen($turn['user_message']) + mb_strlen($turn['assistant_message']),
            $turns,
        ));
    }
}
