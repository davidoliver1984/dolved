<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\IngestionEventClaim;
use App\Models\WorkspaceCorpusGenerationChunk;
use App\Services\Ingestion\IngestionAttemptAuthorizer;
use Illuminate\Support\Facades\DB;

class CompleteIngestionAttempt
{
    public function __construct(
        private readonly IngestionAttemptAuthorizer $authorizer,
        private readonly RecordIngestionAudit $audit,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): IngestionEventClaim
    {
        return DB::transaction(function () use ($eventId, $payload): IngestionEventClaim {
            $attempt = IngestionEventClaim::query()->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
            $this->authorizer->assert($attempt, $payload['event_id'], $payload['workspace_id'], $payload['document_id'], $payload['lease_token'], allowCompleted: true);
            $evidence = collect($payload)->only(array_keys($attempt->publication_evidence ?? []))->all();

            if ($attempt->status === IngestionAttemptStatus::Completed) {
                if ($evidence === $attempt->publication_evidence) {
                    return $attempt;
                }
                throw IngestionAttemptException::invalid('completion_conflict', 'The attempt was completed with different evidence.');
            }
            if ($attempt->status !== IngestionAttemptStatus::PublicationAuthorised || $evidence !== $attempt->publication_evidence) {
                throw IngestionAttemptException::invalid('publication_evidence_mismatch', 'Completion does not match the durable publication authorisation.');
            }
            if (($payload['publication_verified'] ?? false) !== true) {
                throw IngestionAttemptException::invalid('publication_not_verified', 'Completion requires post-publication verification.', 422);
            }

            $document = $attempt->document()->lockForUpdate()->firstOrFail();
            if ($document->status !== DocumentStatus::Processing) {
                throw IngestionAttemptException::invalid('document_ineligible', 'The Document is not eligible for completion.');
            }
            $generation = $attempt->workspaceCorpusGeneration()->lockForUpdate()->firstOrFail();
            foreach ($attempt->chunks()->pluck('id') as $chunkId) {
                WorkspaceCorpusGenerationChunk::query()->firstOrCreate([
                    'workspace_id' => $attempt->workspace_id,
                    'workspace_corpus_generation_id' => $generation->id,
                    'document_chunk_id' => $chunkId,
                ]);
            }
            if ($generation->status !== WorkspaceCorpusGenerationStatus::Active) {
                if ($generation->sparse_space_generation_id !== null) {
                    throw IngestionAttemptException::invalid(
                        'hybrid_generation_not_activated',
                        'A hybrid corpus generation must be verified and activated by the coordinated rebuild workflow.',
                    );
                }
                $generation->forceFill([
                    'status' => WorkspaceCorpusGenerationStatus::Active,
                    'activated_at' => now(),
                ])->save();
            }
            $workspace = $attempt->workspace()->lockForUpdate()->firstOrFail();
            if ($workspace->active_workspace_corpus_generation_id !== null
                && $workspace->active_workspace_corpus_generation_id !== $generation->id) {
                throw IngestionAttemptException::invalid(
                    'active_generation_conflict',
                    'The workspace already has a different active corpus generation.',
                );
            }
            $workspace->forceFill([
                'active_workspace_corpus_generation_id' => $generation->id,
            ])->save();
            $document->forceFill(['status' => DocumentStatus::Indexed])->save();
            $attempt->forceFill([
                'status' => IngestionAttemptStatus::Completed,
                'completed_at' => now(),
            ])->save();
            $this->audit->handle($attempt, 'publication_completed', 'indexed');

            return $attempt;
        });
    }
}
