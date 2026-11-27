<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentApplicabilityScope;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Exceptions\DifyKnowledgeException;
use App\Models\DifyDocumentBinding;
use App\Models\DifyWorkspaceBinding;
use App\Models\Document;
use App\Services\Dify\DifyKnowledgeClient;
use App\Services\Documents\DocumentObjectStorage;
use App\Support\Documents\DocumentAuthorityTimeline;
use Throwable;

final readonly class SyncDifyDocument
{
    public function __construct(
        private DifyKnowledgeClient $dify,
        private DocumentObjectStorage $storage,
        private DocumentAuthorityTimeline $timeline,
    ) {}

    /** Returns false while Dify indexing is still in progress. */
    public function handle(int $documentId): bool
    {
        $document = Document::query()->with([
            'workspace',
            'family.documents',
            'applicabilitySnapshot.locations',
        ])->findOrFail($documentId);
        $workspaceBinding = DifyWorkspaceBinding::query()
            ->where('workspace_id', $document->workspace_id)
            ->where('enabled', true)
            ->first();
        if (! $workspaceBinding instanceof DifyWorkspaceBinding) {
            return true;
        }

        if (! $this->eligible($document)) {
            $this->disableBindings($document->difyBindings()->get());

            return true;
        }

        // Disable every previous or future version before the current version can be enabled.
        $otherBindings = DifyDocumentBinding::query()
            ->where('workspace_id', $document->workspace_id)
            ->whereHas('document', fn ($query) => $query
                ->where('document_family_id', $document->document_family_id)
                ->whereKeyNot($document->id))
            ->get();
        $this->disableBindings($otherBindings);

        $scopeKeys = $this->scopeKeys($document);
        if ($scopeKeys === []) {
            $this->disableBindings($document->difyBindings()->get());

            return true;
        }

        $complete = true;
        foreach ($scopeKeys as $scopeKey) {
            $complete = $this->syncScope($document, $workspaceBinding, $scopeKey) && $complete;
        }
        $this->disableBindings($document->difyBindings()->whereNotIn('scope_key', $scopeKeys)->get());

        $requiredBindings = $document->difyBindings()->whereIn('scope_key', $scopeKeys)->get();
        if ($complete && $requiredBindings->count() === count($scopeKeys)
            && $requiredBindings->every(fn (DifyDocumentBinding $binding): bool => $binding->sync_status === 'available')) {
            $document->forceFill([
                'status' => DocumentStatus::Indexed,
                'failure_category' => null,
                'failure_message' => null,
            ])->save();
        }

        return $complete;
    }

    private function eligible(Document $document): bool
    {
        $eligibleStatuses = config('documents.processing_driver') === 'dify'
            ? [DocumentStatus::Uploaded, DocumentStatus::Processing, DocumentStatus::Indexed]
            : [DocumentStatus::Indexed];

        return in_array($document->status, $eligibleStatuses, true)
            && $document->governance_status === DocumentGovernanceStatus::Approved
            && $document->checksum_verification_status === ChecksumVerificationStatus::Verified
            && is_string($document->source_checksum_sha256)
            && $this->timeline->resolve($document->family, now())?->id === $document->id;
    }

    /** @return array<int, string> */
    private function scopeKeys(Document $document): array
    {
        $snapshot = $document->applicabilitySnapshot;
        if ($snapshot === null) {
            return [];
        }
        if ($snapshot->scope === DocumentApplicabilityScope::Universal) {
            return ['global'];
        }

        return $snapshot->locations->pluck('public_id')->map(fn ($id): string => (string) $id)->all();
    }

    private function syncScope(Document $document, DifyWorkspaceBinding $workspace, string $scopeKey): bool
    {
        $remoteName = $this->remoteName($document, $scopeKey);
        $binding = DifyDocumentBinding::query()->firstOrCreate(
            ['document_id' => $document->id, 'scope_key' => $scopeKey],
            [
                'workspace_id' => $document->workspace_id,
                'dataset_id' => $workspace->dataset_id,
                'remote_name' => $remoteName,
                'sync_status' => 'pending',
                'source_checksum_sha256' => $document->source_checksum_sha256,
            ],
        );
        if (! hash_equals($binding->source_checksum_sha256, (string) $document->source_checksum_sha256)
            || ! hash_equals($binding->dataset_id, $workspace->dataset_id)
            || ! hash_equals($binding->remote_name, $remoteName)) {
            throw new DifyKnowledgeException('binding_identity_conflict');
        }

        if ($document->status === DocumentStatus::Uploaded) {
            $document->forceFill([
                'status' => DocumentStatus::Processing,
                'failure_category' => null,
                'failure_message' => null,
            ])->save();
        }

        if ($binding->sync_status === 'retry_pending' && $binding->remote_document_id !== null) {
            $this->retryScope($document, $binding);
        }

        if ($binding->remote_document_id === null) {
            $matches = $this->dify->findDocuments($workspace->dataset_id, $remoteName);
            if (count($matches) > 1) {
                $binding->forceFill(['sync_status' => 'failed', 'failure_code' => 'ambiguous_remote_identity'])->save();
                throw new DifyKnowledgeException('ambiguous_remote_identity');
            }
            if (count($matches) === 1) {
                $remoteId = $matches[0]['id'] ?? null;
                if (! is_string($remoteId)) {
                    throw new DifyKnowledgeException('invalid_document_list_response');
                }
                $binding->forceFill(['remote_document_id' => $remoteId, 'sync_status' => 'indexing'])->save();
            } else {
                if ($binding->sync_status === 'uncertain' && $binding->updated_at?->addMinutes(2)->isFuture()) {
                    return false;
                }
                $binding->forceFill(['sync_status' => 'uploading', 'failure_code' => null])->save();
                $stream = $this->storage->readStream($document);
                try {
                    $created = $this->dify->createDocument($workspace->dataset_id, $remoteName, $stream);
                } catch (Throwable $exception) {
                    $binding->forceFill(['sync_status' => 'uncertain', 'failure_code' => 'upload_dispatch_uncertain'])->save();
                    throw $exception;
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $binding->forceFill([
                    'remote_document_id' => $created['document_id'],
                    'remote_batch_id' => $created['batch_id'],
                    'sync_status' => 'indexing',
                ])->save();
                $this->dify->changeStatus($workspace->dataset_id, $created['document_id'], 'disable');
            }
        }

        $binding->refresh();
        if ($binding->sync_status === 'available') {
            return true;
        }
        if ($binding->remote_batch_id === null) {
            $remote = $this->dify->findDocuments($workspace->dataset_id, $remoteName);
            $status = $remote[0]['indexing_status'] ?? null;
        } else {
            $statuses = $this->dify->indexingStatus($workspace->dataset_id, $binding->remote_batch_id);
            $match = collect($statuses)->firstWhere('id', $binding->remote_document_id);
            $status = is_array($match) ? ($match['indexing_status'] ?? null) : null;
        }
        if ($status === 'error') {
            $binding->forceFill(['sync_status' => 'failed', 'failure_code' => 'dify_indexing_error'])->save();
            $document->forceFill([
                'status' => DocumentStatus::Failed,
                'failure_category' => 'dify_indexing_error',
                'failure_message' => 'The searchable copy could not be indexed. An administrator can retry it.',
            ])->save();

            return true;
        }
        if ($status !== 'completed') {
            return false;
        }

        $this->dify->setScopeMetadata(
            $workspace->dataset_id,
            $binding->remote_document_id,
            $workspace->scope_metadata_id,
            $scopeKey,
        );
        $this->dify->changeStatus($workspace->dataset_id, $binding->remote_document_id, 'enable');
        try {
            $segmentCount = $this->dify->segmentCount($workspace->dataset_id, $binding->remote_document_id);
            $segmentObservation = [
                'indexed_segment_count' => $segmentCount,
                'segment_count_status' => 'available',
                'segment_count_observed_at' => now(),
            ];
        } catch (Throwable) {
            $segmentObservation = [
                'indexed_segment_count' => null,
                'segment_count_status' => 'unavailable',
                'segment_count_observed_at' => now(),
            ];
        }
        $binding->forceFill([
            'sync_status' => 'available',
            'failure_code' => null,
            'synced_at' => now(),
            ...$segmentObservation,
        ])->save();

        return true;
    }

    private function retryScope(Document $document, DifyDocumentBinding $binding): void
    {
        $this->dify->changeStatus($binding->dataset_id, $binding->remote_document_id, 'disable');
        $stream = $this->storage->readStream($document);
        try {
            $updated = $this->dify->updateDocument(
                $binding->dataset_id,
                $binding->remote_document_id,
                $binding->remote_name,
                $stream,
            );
        } catch (Throwable $exception) {
            $binding->forceFill([
                'sync_status' => 'failed',
                'failure_code' => 'retry_dispatch_uncertain',
            ])->save();
            $document->forceFill([
                'status' => DocumentStatus::Failed,
                'failure_category' => 'retry_dispatch_uncertain',
                'failure_message' => 'The retry outcome could not be confirmed. The searchable copy remains disabled.',
            ])->save();
            throw $exception;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $binding->forceFill([
            'remote_batch_id' => $updated['batch_id'],
            'sync_status' => 'indexing',
            'failure_code' => null,
        ])->save();
        $document->forceFill([
            'status' => DocumentStatus::Processing,
            'failure_category' => null,
            'failure_message' => null,
        ])->save();
    }

    /** @param iterable<int, DifyDocumentBinding> $bindings */
    private function disableBindings(iterable $bindings): void
    {
        foreach ($bindings as $binding) {
            if ($binding->remote_document_id !== null && ! in_array($binding->sync_status, ['disabled', 'deleted'], true)) {
                $this->dify->changeStatus($binding->dataset_id, $binding->remote_document_id, 'disable');
            }
            if ($binding->sync_status !== 'deleted') {
                $binding->forceFill(['sync_status' => 'disabled'])->save();
            }
        }
    }

    private function remoteName(Document $document, string $scopeKey): string
    {
        $extension = pathinfo($document->source_filename, PATHINFO_EXTENSION);
        $suffix = $extension === '' ? '' : '.'.mb_strtolower($extension);

        return sprintf('dolved-%s-%s-%s%s', $document->public_id, substr(hash('sha256', $scopeKey), 0, 12), substr((string) $document->source_checksum_sha256, 0, 12), $suffix);
    }
}
