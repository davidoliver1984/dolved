<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\AdvanceDocumentDeletion as AdvanceAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdvanceDocumentDeletion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 100;

    public function __construct(public readonly int $operationId)
    {
        $this->onConnection('conversation');
        $this->onQueue((string) config('documents.administration_queue'));
        $this->afterCommit();
    }

    public function handle(AdvanceAction $action): void
    {
        if (! $action->handle($this->operationId)) {
            $this->release((int) config('documents.deletion_quiescence_retry_seconds'));
        }
    }
}
