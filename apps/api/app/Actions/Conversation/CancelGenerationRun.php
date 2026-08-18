<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\GenerationRunStatus;
use App\Exceptions\ConversationException;
use App\Models\GenerationRun;
use Illuminate\Support\Facades\DB;

final readonly class CancelGenerationRun
{
    public function handle(GenerationRun $run): GenerationRun
    {
        return DB::transaction(function () use ($run): GenerationRun {
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
            } else {
                $locked->update([
                    'status' => GenerationRunStatus::CancellationRequested,
                    'cancellation_requested_at' => now(),
                ]);
            }

            return $locked->fresh();
        });
    }
}
