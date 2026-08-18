<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\GenerationRunFailureCode;
use App\Enums\GenerationRunStatus;
use App\Models\GenerationRun;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileStaleGenerationRuns
{
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
                DB::transaction(function () use ($runId, &$reconciled): void {
                    $run = GenerationRun::query()->lockForUpdate()->find($runId);
                    if (! $run instanceof GenerationRun || $run->status->isTerminal()) {
                        return;
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
                });
            });

        return $reconciled;
    }
}
