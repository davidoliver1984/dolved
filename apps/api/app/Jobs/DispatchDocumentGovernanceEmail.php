<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\AssembleDocumentGovernanceEmailEnvelope;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\DocumentGovernanceNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchDocumentGovernanceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 240];

    public function __construct(public readonly int $notificationId)
    {
        $this->onConnection('governance');
        $this->onQueue((string) config('documents.governance_queue', 'document-governance'));
        $this->afterCommit();
    }

    public function handle(AssembleDocumentGovernanceEmailEnvelope $assemble): void
    {
        $envelope = $assemble->handle(DocumentGovernanceNotification::query()->findOrFail($this->notificationId));
        if ($envelope?->assembly_status === GovernanceEmailEnvelopeStatus::Ready) {
            DispatchDocumentGovernanceEmailEnvelope::dispatch($envelope->id);
        }
    }
}
