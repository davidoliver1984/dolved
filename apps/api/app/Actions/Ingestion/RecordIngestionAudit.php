<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Models\IngestionAuditEvent;
use App\Models\IngestionEventClaim;

class RecordIngestionAudit
{
    /** @param array<string, mixed> $context */
    public function handle(
        IngestionEventClaim $attempt,
        string $action,
        string $outcome,
        array $context = [],
    ): void {
        $attempt->loadMissing([
            'embeddingSpaceGeneration:id,public_id',
            'workspaceCorpusGeneration:id,public_id',
        ]);
        IngestionAuditEvent::query()->firstOrCreate(
            [
                'event_id' => $attempt->event_id,
                'action' => $action,
                'outcome' => $outcome,
            ],
            [
                'workspace_id' => $attempt->workspace_id,
                'document_id' => $attempt->document_id,
                'context' => [
                    'actor_principal' => 'ingestion-worker',
                    'embedding_space_generation_id' => $attempt->embeddingSpaceGeneration?->public_id,
                    'workspace_corpus_generation_id' => $attempt->workspaceCorpusGeneration?->public_id,
                    ...$context,
                ],
                'occurred_at' => now(),
            ],
        );
    }
}
