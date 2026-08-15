<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\ResolveEngineeringBenchmarkPolicy;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkSource;
use App\Support\Evaluation\EngineeringBenchmarkState;
use App\Support\Evaluation\Exp0005Definition;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class PreflightExp0005EngineeringExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:preflight-exp-0005';

    protected $description = 'Verify EXP-0005 database, population, policy and generation lineage without providers';

    public function handle(
        EngineeringBenchmarkSource $source,
        EngineeringBenchmarkState $states,
        ResolveEngineeringBenchmarkPolicy $policies,
    ): int {
        try {
            $state = $states->read();
            Exp0005Definition::assertProvisioning($state);
            $corpus = $source->engineeringCorpus();
            if (
                ($corpus['snapshot_digest'] ?? null) !== Exp0005Definition::lineage()['engineering_split']['snapshot_digest']
                || ($corpus['case_count'] ?? null) !== EngineeringBenchmark::EXPECTED_ENGINEERING_CASES
                || ($corpus['variant_count'] ?? null) !== EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS
            ) {
                throw new RuntimeException('EXP-0005 engineering population lineage is invalid.');
            }

            $workspace = Workspace::query()
                ->where('public_id', $state['workspace']['public_id'] ?? null)
                ->where('slug', EngineeringBenchmark::WORKSPACE_SLUG)
                ->with([
                    'activeCorpusGeneration.embeddingSpaceGeneration.embeddingProfile',
                    'activeCorpusGeneration.sparseSpaceGeneration.sparseEmbeddingProfile',
                ])
                ->sole();
            $generation = $workspace->activeCorpusGeneration;
            if (
                $generation === null
                || $generation->public_id !== Exp0005Definition::ACTIVE_GENERATION_PUBLIC_ID
                || $generation->status !== WorkspaceCorpusGenerationStatus::Active
                || $generation->expected_point_count !== 99
                || $generation->point_manifest_digest !== Exp0005Definition::POINT_MANIFEST_DIGEST
                || $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint !== Exp0005Definition::EMBEDDING_PROFILE_FINGERPRINT
                || $generation->sparseSpaceGeneration?->sparseEmbeddingProfile->fingerprint !== Exp0005Definition::SPARSE_PROFILE_FINGERPRINT
                || DocumentChunk::query()->where('workspace_id', $workspace->id)->count() !== 99
            ) {
                throw new RuntimeException('EXP-0005 active corpus generation is not the verified 99-point hybrid generation.');
            }

            $policy = $policies->handle($generation);
            Exp0005Definition::assertPolicy($policy);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s preflight verified 42 cases, 126 variants, 99 canonical chunks and the active hybrid generation.',
            Exp0005Definition::RUN_ID,
        ));

        return self::SUCCESS;
    }
}
