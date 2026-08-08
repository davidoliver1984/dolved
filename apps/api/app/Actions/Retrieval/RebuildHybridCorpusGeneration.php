<?php

declare(strict_types=1);

namespace App\Actions\Retrieval;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\RetrievalException;
use App\Models\DocumentChunk;
use App\Models\SparseSpaceGeneration;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Services\Ingestion\DeterministicVectorPointIdentity;
use App\Services\Ingestion\IngestionCanonicaliser;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RebuildHybridCorpusGeneration
{
    public function __construct(
        private RetrievalClient $client,
        private DeterministicVectorPointIdentity $pointIdentity,
        private IngestionCanonicaliser $canonicaliser,
    ) {}

    public function handle(
        Workspace $workspace,
        SparseSpaceGeneration $sparseSpace,
        int $batchSize = 50,
    ): WorkspaceCorpusGeneration {
        if ($batchSize < 1 || $batchSize > 100) {
            throw new RetrievalException('Corpus rebuild batch size must be between 1 and 100.');
        }
        $workspace->loadMissing('activeCorpusGeneration');
        $source = $workspace->activeCorpusGeneration;
        if ($source === null) {
            throw new RetrievalException('A hybrid rebuild requires an active source corpus generation.');
        }
        if ($source->sparse_space_generation_id === $sparseSpace->id) {
            return $source;
        }
        if (
            $sparseSpace->status !== EmbeddingSpaceGenerationStatus::Available
            || $sparseSpace->embedding_space_generation_id !== $source->embedding_space_generation_id
        ) {
            throw new RetrievalException('The sparse space is not available or dense-compatible.');
        }

        $target = DB::transaction(function () use ($workspace, $source, $sparseSpace): WorkspaceCorpusGeneration {
            WorkspaceCorpusGeneration::query()->lockForUpdate()->findOrFail($source->id);
            $existing = WorkspaceCorpusGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->where('rebuilt_from_generation_id', $source->id)
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
                'embedding_space_generation_id' => $source->embedding_space_generation_id,
                'rebuilt_from_generation_id' => $source->id,
                'rebuild_event_id' => (string) Str::uuid(),
                'sparse_space_generation_id' => $sparseSpace->id,
                'status' => WorkspaceCorpusGenerationStatus::Building,
            ]);
            $generation->public_id = (string) Str::uuid();
            $generation->save();
            $now = now();
            $assignments = $source->chunkAssignments()->get(['workspace_id', 'document_chunk_id'])
                ->map(fn ($assignment): array => [
                    'workspace_id' => $assignment->workspace_id,
                    'workspace_corpus_generation_id' => $generation->id,
                    'document_chunk_id' => $assignment->document_chunk_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
            if ($assignments === []) {
                throw new RetrievalException('A corpus generation with no canonical chunks cannot be rebuilt.');
            }
            DB::table('workspace_corpus_generation_chunks')->insertOrIgnore($assignments);

            return $generation;
        });

        $target->load([
            'embeddingSpaceGeneration.embeddingProfile',
            'sparseSpaceGeneration.sparseEmbeddingProfile',
            'documentChunks.document',
        ]);
        $allPointIds = [];
        $target->documentChunks->sortBy('public_id')->chunk($batchSize)
            ->each(function ($chunks) use ($workspace, $target, &$allPointIds): void {
                /** @var list<DocumentChunk> $batch */
                $batch = $chunks->values()->all();
                $returned = $this->client->rebuildCorpusBatch($workspace, $target, $batch);
                $expected = array_map(fn (DocumentChunk $chunk): string => $this->pointIdentity->forChunk(
                    $target->embeddingSpaceGeneration->public_id,
                    $workspace->public_id,
                    $target->public_id,
                    $chunk->public_id,
                ), $batch);
                sort($returned, SORT_STRING);
                sort($expected, SORT_STRING);
                if ($returned !== $expected) {
                    throw new RetrievalException('A corpus rebuild batch returned unexpected point identities.');
                }
                array_push($allPointIds, ...$returned);
            });

        if ($target->status === WorkspaceCorpusGenerationStatus::Building) {
            $target->forceFill(['status' => WorkspaceCorpusGenerationStatus::Verifying])->save();
        }
        $verification = $this->client->verifyCorpus($workspace, $target->fresh());
        sort($verification['point_ids'], SORT_STRING);
        sort($allPointIds, SORT_STRING);
        if (! $verification['complete'] || $verification['point_ids'] !== $allPointIds) {
            throw new RetrievalException('The rebuilt hybrid corpus is not complete across both vector axes.');
        }
        $digest = $this->canonicaliser->pointManifestDigest($allPointIds);

        return DB::transaction(function () use ($workspace, $source, $target, $allPointIds, $digest): WorkspaceCorpusGeneration {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $lockedSource = WorkspaceCorpusGeneration::query()->lockForUpdate()->findOrFail($source->id);
            $lockedTarget = WorkspaceCorpusGeneration::query()->lockForUpdate()->findOrFail($target->id);
            if (
                $lockedWorkspace->active_workspace_corpus_generation_id !== $lockedSource->id
                || $lockedSource->status !== WorkspaceCorpusGenerationStatus::Active
                || $lockedTarget->status !== WorkspaceCorpusGenerationStatus::Verifying
            ) {
                throw new RetrievalException('Corpus activation state changed during the rebuild.');
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
                'embeddingSpaceGeneration',
                'sparseSpaceGeneration',
                'documentChunks',
            ]);
        });
    }
}
