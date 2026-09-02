<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\ReconcileOwnershipEligibility;
use App\Models\OwnershipEligibilityReconciliation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReconcileOwnershipEligibilityAfterMembershipChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $reconciliationId)
    {
        $this->onConnection('governance');
        $this->onQueue((string) config('documents.governance_queue', 'document-governance'));
        $this->afterCommit();
    }

    public function handle(ReconcileOwnershipEligibility $reconcile): void
    {
        $work = OwnershipEligibilityReconciliation::query()->findOrFail($this->reconciliationId);
        $reconcile->handle($work);
        if ($work->refresh()->completed_at === null) {
            self::dispatch($work->id);
        }
    }
}
