<?php

declare(strict_types=1);

namespace App\Actions\Retrieval;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\RetrievalException;
use App\Models\DocumentChunk;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseSpaceGeneration;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusMaterialisationBatch;
use App\Services\Ingestion\DeterministicVectorPointIdentity;
use App\Services\Ingestion\IngestionCanonicaliser;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class MaterialiseWorkspaceCorpusGeneration
{
    public function __construct(
        private RetrievalClient $client,
        private DeterministicVectorPointIdentity $pointIdentity,
        private IngestionCanonicaliser $canonicaliser,
    ) {}

    public function handle(
        Workspace $workspace,
        EmbeddingSpaceGeneration $embeddingSpace,
        SparseSpaceGeneration $sparseSpace,
        int $batchSize = 100,
    ): WorkspaceCorpusGeneration {
        if ($batchSize < 1 || $batchSize > 100) {
            throw new RetrievalException('Corpus materialisation batch size must be between 1 and 100.');
        }
        $workspace->loadMissing('activeCorpusGeneration');
        $source = $workspace->activeCorpusGeneration;
        if ($source === null) {
            throw new RetrievalException('Corpus materialisation requires an active source generation.');
        }
        if (
            $source->embedding_space_generation_id === $embeddingSpace->id
            && $source->sparse_space_generation_id === $sparseSpace->id
        ) {
            return $source;
        }
        if (
            $embeddingSpace->status !== EmbeddingSpaceGenerationStatus::Available
            || $sparseSpace->status !== EmbeddingSpaceGenerationStatus::Available
            || $sparseSpace->embedding_space_generation_id !== $embeddingSpace->id
        ) {
            throw new RetrievalException('Corpus materialisation requires compatible available dense and sparse spaces.');
        }

        $target = DB::transaction(function () use ($workspace, $source, $embeddingSpace, $sparseSpace): WorkspaceCorpusGeneration {
            Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $lockedSource = WorkspaceCorpusGeneration::query()->lockForUpdate()->findOrFail($source->id);
            if ($lockedSource->workspace_id !== $workspace->id) {
                throw new RetrievalException('The source corpus does not belong to the authorised workspace.');
            }
            $existing = WorkspaceCorpusGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->where('rebuilt_from_generation_id', $source->id)
                ->where('embedding_space_generation_id', $embeddingSpace->id)
                ->where('sparse_space_generation_id', $sparseSpace->id)
                ->whereIn('status', [
                    WorkspaceCorpusGenerationStatus::Building->value,
                    WorkspaceCorpusGenerationStatus::Verifying->value,
                ])
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $generation = new WorkspaceCorpusGeneration([
                'workspace_id' => $workspace->id,
                'embedding_space_generation_id' => $embeddingSpace->id,
                'rebuilt_from_generation_id' => $source->id,
                'rebuild_event_id' => (string) Str::uuid(),
                'sparse_space_generation_id' => $sparseSpace->id,
                'status' => WorkspaceCorpusGenerationStatus::Building,
            ]);
            $generation->public_id = (string) Str::uuid();
            $generation->save();
            $now = now();
            $assignments = $source->chunkAssignments()->get(['workspace_id', 'document_chunk_id'])
                ->map(function ($assignment) use ($workspace, $generation, $now): array {
                    if ($assignment->workspace_id !== $workspace->id) {
                        throw new RetrievalException('A source chunk assignment crosses the authorised workspace boundary.');
                    }

                    return [
                        'workspace_id' => $workspace->id,
                        'workspace_corpus_generation_id' => $generation->id,
                        'document_chunk_id' => $assignment->document_chunk_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();
            if ($assignments === []) {
                throw new RetrievalException('A corpus generation with no canonical chunks cannot be materialised.');
            }
            DB::table('workspace_corpus_generation_chunks')->insert($assignments);

            return $generation;
        });

        $target->load([
            'embeddingSpaceGeneration.embeddingProfile',
            'sparseSpaceGeneration.sparseEmbeddingProfile',
            'documentChunks.document',
        ]);
        $allPointIds = [];
        $target->documentChunks->sortBy('public_id')->values()->chunk($batchSize)
            ->each(function ($chunks, int $index) use ($workspace, $target, &$allPointIds): void {
                /** @var list<DocumentChunk> $batch */
                $batch = $chunks->values()->all();
                $chunkIds = array_map(static fn (DocumentChunk $chunk): string => $chunk->public_id, $batch);
                $inputDigest = hash('sha256', implode("\n", $chunkIds));
                $checkpoint = DB::transaction(function () use ($workspace, $target, $index, $batch, $inputDigest): WorkspaceCorpusMaterialisationBatch {
                    $existing = WorkspaceCorpusMaterialisationBatch::query()
                        ->where('workspace_corpus_generation_id', $target->id)
                        ->where('batch_number', $index + 1)
                        ->lockForUpdate()
                        ->first();
                    if ($existing !== null) {
                        if (
                            $existing->workspace_id !== $workspace->id
                            || $existing->input_count !== count($batch)
                            || $existing->input_identity_digest !== $inputDigest
                        ) {
                            throw new RetrievalException('A corpus materialisation checkpoint does not match its deterministic batch.');
                        }

                        return $existing;
                    }
                    $created = new WorkspaceCorpusMaterialisationBatch([
                        'workspace_id' => $workspace->id,
                        'workspace_corpus_generation_id' => $target->id,
                        'batch_number' => $index + 1,
                        'request_id' => (string) Str::uuid(),
                        'input_count' => count($batch),
                        'input_identity_digest' => $inputDigest,
                        'status' => 'pending',
                    ]);
                    $created->public_id = (string) Str::uuid();
                    $created->save();

                    return $created;
                });
                $expected = array_map(fn (DocumentChunk $chunk): string => $this->pointIdentity->forChunk(
                    $target->embeddingSpaceGeneration->public_id,
                    $workspace->public_id,
                    $target->public_id,
                    $chunk->public_id,
                ), $batch);
                $expectedDigest = $this->canonicaliser->pointManifestDigest($expected);
                if ($checkpoint->status === 'completed') {
                    if ($checkpoint->point_manifest_digest !== $expectedDigest) {
                        throw new RetrievalException('A completed corpus materialisation checkpoint has invalid point evidence.');
                    }
                    array_push($allPointIds, ...$expected);

                    return;
                }

                $returned = $this->client->rebuildCorpusBatch(
                    $workspace,
                    $target,
                    $batch,
                    $checkpoint->request_id,
                );
                if ($returned !== $expected) {
                    throw new RetrievalException('A corpus materialisation batch returned missing, duplicate or reordered point identities.');
                }
                DB::transaction(function () use ($checkpoint, $expectedDigest): void {
                    $locked = WorkspaceCorpusMaterialisationBatch::query()->lockForUpdate()->findOrFail($checkpoint->id);
                    if ($locked->status === 'completed') {
                        if ($locked->point_manifest_digest !== $expectedDigest) {
                            throw new RetrievalException('Concurrent batch completion evidence does not match.');
                        }

                        return;
                    }
                    $locked->forceFill([
                        'status' => 'completed',
                        'point_manifest_digest' => $expectedDigest,
                        'completed_at' => now(),
                    ])->save();
                });
                array_push($allPointIds, ...$expected);
            });

        if ($target->status === WorkspaceCorpusGenerationStatus::Building) {
            $target->forceFill(['status' => WorkspaceCorpusGenerationStatus::Verifying])->save();
        }
        $verification = $this->client->verifyCorpus($workspace, $target->fresh());
        $expectedSorted = $allPointIds;
        $returnedSorted = $verification['point_ids'];
        sort($expectedSorted, SORT_STRING);
        sort($returnedSorted, SORT_STRING);
        if (! $verification['complete'] || $returnedSorted !== $expectedSorted) {
            throw new RetrievalException('The materialised corpus is not complete across both vector axes.');
        }
        $digest = $this->canonicaliser->pointManifestDigest($allPointIds);

        return DB::transaction(function () use ($workspace, $source, $target, $allPointIds, $digest): WorkspaceCorpusGeneration {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $lockedSource = WorkspaceCorpusGeneration::query()->lockForUpdate()->findOrFail($source->id);
            $lockedTarget = WorkspaceCorpusGeneration::query()->lockForUpdate()->findOrFail($target->id);
            $completedCount = $lockedTarget->materialisationBatches()->where('status', 'completed')->sum('input_count');
            if (
                $lockedWorkspace->active_workspace_corpus_generation_id !== $lockedSource->id
                || $lockedSource->status !== WorkspaceCorpusGenerationStatus::Active
                || $lockedTarget->status !== WorkspaceCorpusGenerationStatus::Verifying
                || $completedCount !== count($allPointIds)
            ) {
                throw new RetrievalException('Corpus activation state changed during materialisation.');
            }
            $now = now();
            $lockedSource->forceFill([
                'status' => WorkspaceCorpusGenerationStatus::Superseded,
                'superseded_at' => $now,
            ])->save();
            $lockedTarget->forceFill([
                'expected_point_count' => count($allPointIds),
                'point_manifest_digest' => $digest,
                'verified_at' => $now,
                'status' => WorkspaceCorpusGenerationStatus::Active,
                'activated_at' => $now,
            ])->save();
            $lockedWorkspace->forceFill([
                'active_workspace_corpus_generation_id' => $lockedTarget->id,
            ])->save();

            return $lockedTarget->fresh([
                'embeddingSpaceGeneration.embeddingProfile',
                'sparseSpaceGeneration.sparseEmbeddingProfile',
                'documentChunks',
                'materialisationBatches',
            ]);
        });
    }
}
