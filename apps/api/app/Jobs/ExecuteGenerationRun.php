<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Conversation\OrchestrateConversationRun;
use App\Enums\GenerationRunFailureCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteGenerationRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $generationRunId)
    {
        $this->onConnection('conversation');
        $this->onQueue((string) config('conversation.queue'));
        $this->afterCommit();
    }

    public function handle(OrchestrateConversationRun $orchestration): void
    {
        $orchestration->handle($this->generationRunId);
    }

    public function failed(?Throwable $exception): void
    {
        app(OrchestrateConversationRun::class)->fail(
            $this->generationRunId,
            GenerationRunFailureCode::InternalFailure,
        );
    }
}
