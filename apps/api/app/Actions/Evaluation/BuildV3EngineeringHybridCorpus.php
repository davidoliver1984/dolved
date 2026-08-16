<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Actions\Retrieval\RebuildHybridCorpusGeneration;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\SparseSpaceGeneration;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final readonly class BuildV3EngineeringHybridCorpus
{
    public function __construct(
        private V3EngineeringBenchmarkState $states,
        private RebuildHybridCorpusGeneration $rebuild,
        private RetrievalClient $retrieval,
    ) {}

    /** @return array<string, mixed> */
    public function handle(int $batchSize = 10): array
    {
        $state = $this->states->read();
        if (
            ($state['status'] ?? null) !== 'DENSE_VERIFIED'
            || ($state['benchmark']['population_digest'] ?? null) !== V3EngineeringBenchmark::POPULATION_DIGEST
            || ($state['workspace']['slug'] ?? null) !== V3EngineeringBenchmark::WORKSPACE_SLUG
        ) {
            throw new RuntimeException('Only the verified V3 dense corpus can be rebuilt.');
        }
        $workspace = Workspace::query()->where('public_id', $state['workspace']['public_id'])->sole();
        $dense = $workspace->activeCorpusGeneration()->with('embeddingSpaceGeneration')->firstOrFail();
        if (Artisan::call('retrieval:provision-sparse-space', ['embedding-space' => $dense->embeddingSpaceGeneration->public_id]) !== 0) {
            throw new RuntimeException('V3 sparse-space provisioning failed.');
        }
        $sparse = SparseSpaceGeneration::query()
            ->where('embedding_space_generation_id', $dense->embedding_space_generation_id)
            ->where('status', 'available')
            ->with('sparseEmbeddingProfile')
            ->sole();
        $hybrid = $this->rebuild->handle($workspace, $sparse, $batchSize);
        $verification = $this->retrieval->verifyCorpus($workspace->fresh(), $hybrid->fresh());
        $chunkCount = (int) $state['canonical_chunk_count'];
        if (
            $hybrid->status !== WorkspaceCorpusGenerationStatus::Active
            || ! $verification['complete']
            || count($verification['point_ids']) !== $chunkCount
            || $hybrid->documentChunks()->count() !== $chunkCount
            || $workspace->fresh()->active_workspace_corpus_generation_id !== $hybrid->id
            || $hybrid->embeddingSpaceGeneration->dimensions !== 1024
        ) {
            throw new RuntimeException('The V3 hybrid corpus failed final completeness verification.');
        }
        $state['status'] = 'MATERIALISED';
        $state['materialised_at'] = now()->toIso8601String();
        $state['generations']['sparse_space'] = [
            'public_id' => $sparse->public_id,
            'profile_fingerprint' => $sparse->sparseEmbeddingProfile->fingerprint,
            'vector_name' => $sparse->vector_name,
        ];
        $state['generations']['hybrid_corpus'] = [
            'public_id' => $hybrid->public_id,
            'expected_point_count' => $hybrid->expected_point_count,
            'actual_point_count' => count($verification['point_ids']),
            'point_manifest_digest' => $hybrid->point_manifest_digest,
            'verified_at' => $hybrid->verified_at?->toIso8601String(),
            'point_ids' => $verification['point_ids'],
        ];

        return $this->states->write($state);
    }
}
