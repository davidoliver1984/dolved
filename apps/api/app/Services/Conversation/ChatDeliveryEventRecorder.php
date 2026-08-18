<?php

declare(strict_types=1);

namespace App\Services\Conversation;

use App\Enums\ChatStreamEventType;
use App\Exceptions\GenerationException;
use App\Models\ChatDeliveryEvent;
use App\Models\GenerationRun;
use Illuminate\Support\Facades\DB;

final class ChatDeliveryEventRecorder
{
    /** @param array<string, mixed> $safePayload */
    public function record(GenerationRun $run, ChatStreamEventType $type, array $safePayload, bool $provisional = false): ChatDeliveryEvent
    {
        return DB::transaction(function () use ($run, $type, $safePayload, $provisional): ChatDeliveryEvent {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->next_event_sequence > (int) config('conversation.delivery_event_limit') && ! $type->isTerminal()) {
                throw new GenerationException('The bounded delivery event limit was exceeded.');
            }
            if ($locked->deliveryEvents()->whereIn('event_type', array_map(
                fn (ChatStreamEventType $event): string => $event->value,
                array_filter(ChatStreamEventType::cases(), fn (ChatStreamEventType $event): bool => $event->isTerminal()),
            ))->exists()) {
                throw new GenerationException('A terminal delivery event already exists.');
            }
            $event = ChatDeliveryEvent::query()->create([
                'workspace_id' => $locked->workspace_id,
                'generation_run_id' => $locked->id,
                'sequence' => $locked->next_event_sequence,
                'event_type' => $type,
                'provisional' => $provisional,
                'safe_payload' => $safePayload,
                'expires_at' => now()->addSeconds((int) config('conversation.delivery_event_retention_seconds')),
            ]);
            $locked->increment('next_event_sequence');

            return $event;
        });
    }
}
