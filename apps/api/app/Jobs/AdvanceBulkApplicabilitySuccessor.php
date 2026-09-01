<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\CreateApplicabilityOnlySuccessor;
use App\Models\Document;
use App\Models\DocumentContentCloneOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class AdvanceBulkApplicabilitySuccessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $predecessorId,
        public readonly int $targetId,
        public readonly ?int $cloneOperationId,
        public readonly ?string $leaseToken,
        public readonly string $correlationId,
        public readonly string $fallbackEventId,
    ) {
        $this->onConnection('conversation');
        $this->onQueue((string) config('documents.administration_queue'));
        $this->afterCommit();
    }

    public function handle(CreateApplicabilityOnlySuccessor $successor): void
    {
        $successor->finish(
            Document::query()->findOrFail($this->predecessorId),
            Document::query()->findOrFail($this->targetId),
            $this->cloneOperationId === null
                ? null
                : DocumentContentCloneOperation::query()->findOrFail($this->cloneOperationId),
            $this->leaseToken,
            $this->correlationId,
            $this->fallbackEventId,
        );
    }
}
