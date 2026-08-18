<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message): array => [
                'id' => $message->public_id,
                'ordinal' => $message->ordinal,
                'role' => $message->role->value,
                'kind' => $message->kind?->value,
                'text' => $message->display_text,
                'in_reply_to_message_id' => $message->inReplyTo?->public_id,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all()),
            'runs' => $this->whenLoaded('generationRuns', fn () => $this->generationRuns->map(fn ($run): array => [
                'id' => $run->public_id,
                'user_message_id' => $run->userMessage?->public_id,
                'assistant_message_id' => $run->assistantMessage?->public_id,
                'retry_of_run_id' => $run->retryOf?->public_id,
                'status' => $run->status->value,
                'failure_code' => $run->failure_code?->value,
                'delivery_mode' => $run->delivery_mode,
                'retryable' => $run->status->isRetryEligible(),
                'created_at' => $run->created_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
