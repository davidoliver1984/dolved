<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\ChatStreamEventType;
use App\Enums\GenerationRunStatus;
use App\Exceptions\ConversationException;
use App\Models\GenerationRun;
use App\Services\Conversation\ChatDeliveryEventRecorder;
use App\Support\Usage\RecordWorkspaceUsage;
use Illuminate\Support\Facades\DB;

final readonly class CancelGenerationRun
{
    public function __construct(private ChatDeliveryEventRecorder $events, private RecordWorkspaceUsage $usage) {}

    public function handle(GenerationRun $run): GenerationRun
    {
        $cancelled = DB::transaction(function () use ($run): GenerationRun {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status->isTerminal()) {
                throw new ConversationException('A terminal generation run cannot be cancelled.');
            }
            if ($locked->status === GenerationRunStatus::CancellationRequested) {
                return $locked;
            }
            if ($locked->status === GenerationRunStatus::Queued) {
                $locked->update([
                    'status' => GenerationRunStatus::Cancelled,
                    'cancellation_requested_at' => now(),
                    'cancellation_acknowledged_at' => now(),
                    'completed_at' => now(),
                ]);
                $this->usage->activity($locked->workspace_id, 'run_outcome', $locked->public_id, GenerationRunStatus::Cancelled->value);
            } else {
                $locked->update([
                    'status' => GenerationRunStatus::CancellationRequested,
                    'cancellation_requested_at' => now(),
                ]);
            }

            return $locked->fresh();
        });
        if ($cancelled->status === GenerationRunStatus::Cancelled
            && ! $cancelled->deliveryEvents()->where('event_type', ChatStreamEventType::RunCancelled->value)->exists()) {
            $this->events->record($cancelled, ChatStreamEventType::RunCancelled, []);
        }

        return $cancelled;
    }
}
