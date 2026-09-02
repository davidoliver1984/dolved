<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\ProjectDocumentGovernanceEvent;
use App\Models\DocumentGovernanceEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProjectDocumentGovernanceNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 240];

    public function __construct(public readonly int $eventId)
    {
        $this->onConnection('governance');
        $this->onQueue((string) config('documents.governance_queue', 'document-governance'));
        $this->afterCommit();
    }

    public function handle(ProjectDocumentGovernanceEvent $project): void
    {
        $project->handle(DocumentGovernanceEvent::query()->findOrFail($this->eventId));
    }
}
