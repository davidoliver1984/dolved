<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentStatus;
use App\Exceptions\DocumentAdministrationException;
use App\Models\DocumentDeletionOperation;
use App\Models\DocumentExtractionArtifact;
use App\Models\IngestionAuditEvent;
use App\Services\Documents\DocumentObjectStorage;
use App\Services\Documents\ExtractionArtifactObjectStorage;
use Illuminate\Support\Facades\DB;

class CompleteDocumentDeletion
{
    public function __construct(
        private readonly DocumentObjectStorage $storage,
        private readonly ExtractionArtifactObjectStorage $artifactStorage,
    ) {}

    public function handle(string $eventId, array $payload): DocumentDeletionOperation
    {
        $operation = DocumentDeletionOperation::query()->with('document.workspace')->where('public_id', $eventId)->firstOrFail();
        if ($operation->status === DocumentDeletionStatus::Completed) {
            return $operation;
        }
        $this->assertLease($operation, $payload);
        $expected = $operation->vector_scopes ?? [];
        $scopes = collect($payload['scopes'])->sortBy('scope_index')->values()->all();
        $expectedIndices = $expected === [] ? [] : range(0, count($expected) - 1);
        if (count($scopes) !== count($expected)
            || array_column($scopes, 'scope_index') !== $expectedIndices
            || collect($scopes)->contains(fn (array $scope): bool => $scope['remaining_point_count'] !== 0)) {
            throw DocumentAdministrationException::conflict('deletion_cleanup_unverified', 'Every authorised vector scope must be verified clean.');
        }

        $this->storage->delete($operation->document);
        DocumentExtractionArtifact::query()
            ->where('document_id', $operation->document_id)
            ->pluck('object_key')
            ->each(fn (string $objectKey) => $this->artifactStorage->deleteExact($objectKey));

        return DB::transaction(function () use ($operation, $payload): DocumentDeletionOperation {
            $locked = DocumentDeletionOperation::query()->with('document')->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === DocumentDeletionStatus::Completed) {
                return $locked;
            }
            $this->assertLease($locked, $payload);
            $chunkIds = $locked->document->chunks()->pluck('id');
            DB::table('workspace_corpus_generation_chunks')->whereIn('document_chunk_id', $chunkIds)->delete();
            $locked->document->chunks()->delete();
            $locked->document->forceFill(['active_extraction_projection_generation_id' => null])->save();
            $locked->document->extractionProjectionGenerations()->delete();
            DocumentExtractionArtifact::query()->where('document_id', $locked->document_id)->delete();
            $locked->document->forceFill([
                'status' => DocumentStatus::Deleted,
                'failure_category' => null,
                'failure_message' => null,
            ])->save();
            $locked->forceFill([
                'status' => DocumentDeletionStatus::Completed,
                'cleanup_evidence' => [
                    ...($locked->cleanup_evidence ?? []),
                    'scopes' => $payload['scopes'],
                    'object_removed' => true,
                    'extraction_artifacts_removed' => true,
                    'extraction_projections_removed' => true,
                    'chunks_removed' => true,
                ],
                'lease_token_hash' => null,
                'lease_expires_at' => null,
                'completed_at' => now(),
            ])->save();
            IngestionAuditEvent::query()->create([
                'event_id' => $locked->public_id,
                'workspace_id' => $locked->workspace_id,
                'document_id' => $locked->document_id,
                'action' => 'deletion_completed',
                'outcome' => 'deleted',
                'context' => ['vector_scope_count' => count($payload['scopes'])],
                'occurred_at' => now(),
            ]);

            return $locked;
        });
    }

    private function assertLease(DocumentDeletionOperation $operation, array $payload): void
    {
        if ($operation->document->public_id !== $payload['document_id']
            || $operation->document->workspace->public_id !== $payload['workspace_id']
            || $operation->lease_token_hash === null
            || ! hash_equals($operation->lease_token_hash, hash('sha256', $payload['lease_token']))
            || $operation->lease_expires_at === null
            || $operation->lease_expires_at->isPast()) {
            throw DocumentAdministrationException::conflict('stale_deletion_lease', 'The deletion lease is stale or does not match the authorised scope.');
        }
    }
}
