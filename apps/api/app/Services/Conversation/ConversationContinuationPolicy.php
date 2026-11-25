<?php

declare(strict_types=1);

namespace App\Services\Conversation;

use App\Enums\ConversationStatus;
use App\Enums\GenerationRunStatus;
use App\Exceptions\ConversationException;
use App\Models\Conversation;
use App\Models\OrganisationalLocation;

final class ConversationContinuationPolicy
{
    public const string REMOTE_HISTORY_INCONSISTENT = 'remote_history_inconsistent';

    public const string LOCATION_UNAVAILABLE = 'location_unavailable';

    public const string REMOTE_HISTORY_MESSAGE = 'This conversation could not be safely continued after the interrupted response. Start a new conversation to continue.';

    public const string LOCATION_UNAVAILABLE_MESSAGE = 'This conversation uses a location that is no longer available. Start a new conversation with an available location to continue.';

    /** @return array{allowed: bool, reason: string|null, message: string|null, start_new_required: bool} */
    public function decision(Conversation $conversation): array
    {
        if ($conversation->status !== ConversationStatus::Active) {
            return $this->blocked('conversation_not_active', 'This conversation is no longer accepting messages. Start a new conversation to continue.');
        }

        if (! $this->locationIsAvailable($conversation)) {
            return $this->blocked(self::LOCATION_UNAVAILABLE, self::LOCATION_UNAVAILABLE_MESSAGE);
        }

        if ($conversation->continuation_blocked_at !== null || $this->hasLegacyRemoteHistoryRisk($conversation)) {
            return $this->blocked(self::REMOTE_HISTORY_INCONSISTENT, self::REMOTE_HISTORY_MESSAGE);
        }

        return ['allowed' => true, 'reason' => null, 'message' => null, 'start_new_required' => false];
    }

    public function assertCanContinue(Conversation $conversation): void
    {
        $decision = $this->decision($conversation);
        if (! $decision['allowed']) {
            throw new ConversationException((string) $decision['message']);
        }
    }

    public function blockForRemoteHistory(Conversation $conversation): void
    {
        if ($conversation->continuation_blocked_at !== null) {
            return;
        }

        $conversation->forceFill([
            'continuation_blocked_at' => now(),
            'continuation_block_reason' => self::REMOTE_HISTORY_INCONSISTENT,
        ])->save();
    }

    private function locationIsAvailable(Conversation $conversation): bool
    {
        if ($conversation->selected_location_id === null) {
            return true;
        }

        return OrganisationalLocation::query()
            ->whereKey($conversation->selected_location_id)
            ->where('workspace_id', $conversation->workspace_id)
            ->whereNull('retired_at')
            ->exists();
    }

    private function hasLegacyRemoteHistoryRisk(Conversation $conversation): bool
    {
        if (! $conversation->difySession()->exists()) {
            return false;
        }

        return $conversation->generationRuns()
            ->where(function ($query): void {
                $query->whereIn('provider_dispatch_state', ['uncertain', 'completed_after_cancel'])
                    ->orWhere(function ($query): void {
                        $query->whereIn('status', [GenerationRunStatus::Failed->value, GenerationRunStatus::Cancelled->value])
                            ->where('provider_dispatch_state', 'dispatching');
                    });
            })
            ->exists();
    }

    /** @return array{allowed: false, reason: string, message: string, start_new_required: true} */
    private function blocked(string $reason, string $message): array
    {
        return ['allowed' => false, 'reason' => $reason, 'message' => $message, 'start_new_required' => true];
    }
}
