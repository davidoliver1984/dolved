<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Enums\IngestionAttemptStatus;
use App\Models\DocumentDeletionOperation;
use App\Models\IngestionEventClaim;
use App\Models\OutboxEvent;
use App\Models\WorkspaceCorpusGeneration;
use App\Support\Documents\DocumentDeletionRequestedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AdvanceDocumentDeletion
{
    public function __construct(private readonly DocumentDeletionRequestedPayload $payloads) {}

    public function handle(int $operationId): bool
    {
        return DB::transaction(function () use ($operationId): bool {
            $operation = DocumentDeletionOperation::query()
                ->with('document.workspace')
                ->whereKey($operationId)
                ->lockForUpdate()
                ->firstOrFail();
            if (in_array($operation->status, [DocumentDeletionStatus::Queued, DocumentDeletionStatus::Processing, DocumentDeletionStatus::Completed], true)) {
                return true;
            }
            if (! $this->isQuiescent($operation)) {
                return false;
            }
            $scopes = $this->vectorScopes($operation);
            $operation->forceFill([
                'status' => DocumentDeletionStatus::Queued,
                'vector_scopes' => $scopes,
                'dispatched_at' => now(),
            ])->save();
            $occurredAt = CarbonImmutable::now();
            $payload = $this->payloads->build($operation->refresh(), $occurredAt);
            OutboxEvent::query()->firstOrCreate(
                ['event_id' => $operation->public_id],
                [
                    'event_type' => DocumentDeletionRequestedPayload::EVENT_TYPE,
                    'event_version' => DocumentDeletionRequestedPayload::EVENT_VERSION,
                    'workspace_public_id' => $operation->document->workspace->public_id,
                    'document_public_id' => $operation->document->public_id,
                    'correlation_id' => $operation->correlation_id,
                    'payload' => $payload,
                    'occurred_at' => $occurredAt,
                ],
            );

            return true;
        });
    }

    private function isQuiescent(DocumentDeletionOperation $operation): bool
    {
        if ($operation->active_attempt_ids === []) {
            return true;
        }

        return IngestionEventClaim::query()
            ->whereIn('id', $operation->active_attempt_ids)
            ->get()
            ->every(fn (IngestionEventClaim $attempt): bool => in_array($attempt->status, [
                IngestionAttemptStatus::Cancelled,
                IngestionAttemptStatus::Failed,
                IngestionAttemptStatus::Completed,
            ], true) || ($attempt->lease_expires_at !== null && $attempt->lease_expires_at->isPast()));
    }

    private function vectorScopes(DocumentDeletionOperation $operation): array
    {
        $attemptGenerationIds = IngestionEventClaim::query()
            ->where('document_id', $operation->document_id)
            ->whereNotNull('workspace_corpus_generation_id')
            ->pluck('workspace_corpus_generation_id');
        $assignmentGenerationIds = DB::table('workspace_corpus_generation_chunks')
            ->join('document_chunks', 'document_chunks.id', '=', 'workspace_corpus_generation_chunks.document_chunk_id')
            ->where('document_chunks.document_id', $operation->document_id)
            ->pluck('workspace_corpus_generation_chunks.workspace_corpus_generation_id');
        $generationIds = $attemptGenerationIds->merge($assignmentGenerationIds)->unique()->values();

        return WorkspaceCorpusGeneration::query()
            ->with([
                'embeddingSpaceGeneration.embeddingProfile',
                'sparseSpaceGeneration.sparseEmbeddingProfile',
                'workspace',
            ])
            ->whereIn('id', $generationIds)
            ->orderBy('id')
            ->get()
            ->map(function (WorkspaceCorpusGeneration $generation) use ($operation): array {
                $embedding = $generation->embeddingSpaceGeneration;
                $sparse = $generation->sparseSpaceGeneration;

                return [
                    'vector_space' => [
                        'collection_name' => $embedding->collection_name,
                        'embedding_space_generation_id' => $embedding->public_id,
                        'profile_fingerprint' => $embedding->embeddingProfile->fingerprint,
                        'vector_name' => $embedding->vector_name,
                        'dimensions' => $embedding->dimensions,
                        'distance' => $embedding->distance,
                        'sparse' => $sparse === null ? null : [
                            'sparse_space_generation_id' => $sparse->public_id,
                            'profile_fingerprint' => $sparse->sparseEmbeddingProfile->fingerprint,
                            'vector_name' => $sparse->vector_name,
                        ],
                    ],
                    'workspace_id' => $generation->workspace->public_id,
                    'workspace_corpus_generation_id' => $generation->public_id,
                    'document_id' => $operation->document->public_id,
                ];
            })
            ->all();
    }
}
