<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\BulkOperations\ProcessBulkOperation;
use App\Models\BulkOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExecuteBulkOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $operationId)
    {
        $this->onConnection('conversation');
        $this->onQueue((string) config('documents.administration_queue'));
        $this->afterCommit();
    }

    public function handle(ProcessBulkOperation $process): void
    {
        $process->handle(BulkOperation::query()->findOrFail($this->operationId));
    }
}
