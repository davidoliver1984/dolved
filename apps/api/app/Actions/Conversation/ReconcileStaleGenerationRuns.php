<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\ChatStreamEventType;
use App\Enums\GenerationRunFailureCode;
use App\Enums\GenerationRunStatus;
use App\Models\GenerationRun;
use App\Services\Conversation\ChatDeliveryEventRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileStaleGenerationRuns
{
    public function __construct(private ChatDeliveryEventRecorder $events) {}

    public function handle(): int
    {
        $reconciled = 0;
        $active = collect(GenerationRunStatus::cases())->reject->isTerminal()->map->value->all();
        GenerationRun::query()
            ->whereIn('status', $active)
            ->where('updated_at', '<=', now()->subSeconds((int) config('conversation.run_timeout_seconds')))
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $runId) use (&$reconciled): void {
                $terminal = DB::transaction(function () use ($runId, &$reconciled): ?GenerationRun {
                    $run = GenerationRun::query()->lockForUpdate()->find($runId);
                    if (! $run instanceof GenerationRun || $run->status->isTerminal()) {
                        return null;
                    }
                    if ($run->status === GenerationRunStatus::CancellationRequested) {
                        $run->update([
                            'status' => GenerationRunStatus::Cancelled,
                            'cancellation_acknowledged_at' => now(),
                            'completed_at' => now(),
                        ]);
                    } else {
                        $run->update([
                            'status' => GenerationRunStatus::Failed,
                            'failure_code' => GenerationRunFailureCode::RunTimeout,
                            'completed_at' => now(),
                        ]);
                    }
                    $reconciled++;

                    return $run;
                });
                if ($terminal instanceof GenerationRun) {
                    $type = $terminal->status === GenerationRunStatus::Cancelled ? ChatStreamEventType::RunCancelled : ChatStreamEventType::RunFailed;
                    $payload = $type === ChatStreamEventType::RunFailed ? ['failure_code' => GenerationRunFailureCode::RunTimeout->value, 'retryable' => true] : [];
                    $this->events->record($terminal, $type, $payload);
                }
            });

        return $reconciled;
    }
}
