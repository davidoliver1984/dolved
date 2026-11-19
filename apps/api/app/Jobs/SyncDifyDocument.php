<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\SyncDifyDocument as SyncAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncDifyDocument implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 120;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $documentId)
    {
        $this->onConnection('conversation');
        $this->onQueue((string) config('dify.document_sync_queue'));
        $this->afterCommit();
    }

    public function handle(SyncAction $sync): void
    {
        if (! $sync->handle($this->documentId)) {
            $this->release(5);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }
}
