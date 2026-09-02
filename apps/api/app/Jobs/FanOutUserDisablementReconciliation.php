<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\FanOutUserDisablement;
use App\Models\UserDisablementReconciliationSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class FanOutUserDisablementReconciliation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $sourceId)
    {
        $this->onConnection('governance');
        $this->onQueue((string) config('documents.governance_queue', 'document-governance'));
        $this->afterCommit();
    }

    public function handle(FanOutUserDisablement $fanOut): void
    {
        $source = UserDisablementReconciliationSource::query()->findOrFail($this->sourceId);
        $fanOut->handle($source);
        if ($source->refresh()->completed_at === null) {
            self::dispatch($source->id);
        }
    }
}
