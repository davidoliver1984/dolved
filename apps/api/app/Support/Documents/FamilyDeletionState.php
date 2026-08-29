<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentContentCloneStatus;
use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Models\Document;
use App\Models\DocumentContentCloneOperation;
use App\Models\DocumentFamily;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class FamilyDeletionState
{
    public function __construct(private DocumentAuthorityTimeline $timeline) {}

    /** @param Collection<int, Document> $versions
     * @return array<string, mixed>
     */
    public function capture(DocumentFamily $family, Collection $versions): array
    {
        $documentIds = $versions->pluck('id');
        $current = $this->timeline->resolve($family, now());
        $openCloneStatuses = [
            DocumentContentCloneStatus::Authorised->value,
            DocumentContentCloneStatus::Copying->value,
            DocumentContentCloneStatus::Verifying->value,
            DocumentContentCloneStatus::CleanupRequired->value,
        ];
        $cloneOperations = DocumentContentCloneOperation::query()
            ->where(function ($query) use ($documentIds): void {
                $query->whereIn('source_document_id', $documentIds)
                    ->orWhereIn('target_document_id', $documentIds);
            })
            ->whereIn('status', $openCloneStatuses)
            ->orderBy('id')
            ->get(['id', 'public_id', 'source_document_id', 'target_document_id', 'status']);
        $activeAttemptStatuses = [
            IngestionAttemptStatus::Open->value,
            IngestionAttemptStatus::Sealed->value,
            IngestionAttemptStatus::PublicationAuthorised->value,
        ];

        $versionState = $versions->map(function (Document $version) use ($current): array {
            $classification = match (true) {
                $version->governance_status === DocumentGovernanceStatus::Draft => 'draft',
                $version->governance_status === DocumentGovernanceStatus::Withdrawn => 'withdrawn',
                $current?->is($version) => 'current',
                $this->timeline->authorityStart($version)?->isFuture() === true => 'scheduled',
                default => 'superseded',
            };

            return [
                'id' => $version->id,
                'public_id' => $version->public_id,
                'classification' => $classification,
                'governance_status' => $version->governance_status->value,
                'technical_status' => $version->status->value,
                'effective_from' => $version->effective_from->toISOString(),
                'approved_at' => $version->approved_at?->toISOString(),
                'withdrawn_at' => $version->withdrawn_at?->toISOString(),
            ];
        })->values()->all();

        $counts = [
            'versions' => $versions->count(),
            'source_objects' => $versions->whereNotIn('status', [DocumentStatus::Deleted])->count(),
            'extraction_artifacts' => DB::table('document_extraction_artifacts')->whereIn('document_id', $documentIds)->count(),
            'projection_generations' => DB::table('document_extraction_projection_generations')->whereIn('document_id', $documentIds)->count(),
            'extraction_warnings' => DB::table('document_extraction_projection_warnings')
                ->join('document_extraction_projection_generations', 'document_extraction_projection_generations.id', '=', 'document_extraction_projection_warnings.projection_generation_id')
                ->whereIn('document_extraction_projection_generations.document_id', $documentIds)->count(),
            'corpus_assignments' => DB::table('workspace_corpus_generation_chunks')
                ->join('document_chunks', 'document_chunks.id', '=', 'workspace_corpus_generation_chunks.document_chunk_id')
                ->whereIn('document_chunks.document_id', $documentIds)->count(),
            'chunks' => DB::table('document_chunks')->whereIn('document_id', $documentIds)->count(),
            'vector_points_expected' => (int) DB::table('ingestion_event_claims')->whereIn('document_id', $documentIds)->sum('expected_point_count'),
            'clone_manifests' => DB::table('document_content_clone_manifests')
                ->join('document_content_clone_operations', 'document_content_clone_operations.id', '=', 'document_content_clone_manifests.document_content_clone_operation_id')
                ->whereIn('document_content_clone_operations.target_document_id', $documentIds)
                ->where('document_content_clone_manifests.cleanup_state', '!=', 'deleted')->count(),
        ];

        return [
            'family' => ['id' => $family->id, 'public_id' => $family->public_id, 'tombstoned_at' => $family->tombstoned_at?->toISOString()],
            'versions' => $versionState,
            'counts' => $counts,
            'blockers' => [
                'open_clone_operations' => $cloneOperations->map(fn (DocumentContentCloneOperation $operation): array => [
                    'public_id' => $operation->public_id,
                    'status' => $operation->status->value,
                ])->all(),
                'active_ingestion_attempts' => DB::table('ingestion_event_claims')->whereIn('document_id', $documentIds)->whereIn('status', $activeAttemptStatuses)->orderBy('id')->pluck('event_id')->all(),
                'open_deletion_operations' => DB::table('document_deletion_operations')->whereIn('document_id', $documentIds)->whereIn('status', [
                    DocumentDeletionStatus::AwaitingQuiescence->value,
                    DocumentDeletionStatus::Queued->value,
                    DocumentDeletionStatus::Processing->value,
                    DocumentDeletionStatus::Failed->value,
                ])->orderBy('id')->pluck('public_id')->all(),
            ],
        ];
    }

    /** @param array<string, mixed> $state */
    public function digest(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
