<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\DocumentChunk;
use App\Models\EvidenceThresholdPolicy;
use App\Models\Workspace;
use App\Support\Evaluation\Exp0007Definition;
use App\Support\Evaluation\V3EngineeringBenchmark;
use App\Support\Evaluation\V3EngineeringBenchmarkSource;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class PreflightExp0007EngineeringExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:preflight-exp-0007';

    protected $description = 'Verify EXP-0007 V3 population, policy, provisioning and generation lineage without providers';

    public function handle(V3EngineeringBenchmarkSource $source, V3EngineeringBenchmarkState $states): int
    {
        try {
            $state = $states->read();
            Exp0007Definition::assertProvisioning($state);
            $inputs = $source->load();
            $lineage = Exp0007Definition::lineage();
            if (
                hash_file('sha256', V3EngineeringBenchmark::root().'/population-manifest.json') !== Exp0007Definition::POPULATION_MANIFEST_SHA256
                || hash_file('sha256', V3EngineeringBenchmark::root().'/corpus.json') !== Exp0007Definition::CORPUS_SHA256
                || hash_file('sha256', V3EngineeringBenchmark::root().'/expectations.json') !== Exp0007Definition::EXPECTATIONS_SHA256
                || hash_file('sha256', V3EngineeringBenchmark::root().'/organisation.json') !== Exp0007Definition::ORGANISATION_SHA256
                || hash_file('sha256', V3EngineeringBenchmark::root().'/document-catalog.json') !== Exp0007Definition::CATALOGUE_SHA256
                || ($inputs['manifest']['population_digest'] ?? null) !== $lineage['engineering_population']['digest']
            ) {
                throw new RuntimeException('EXP-0007 V3 engineering population lineage is invalid.');
            }

            $workspace = Workspace::query()
                ->where('public_id', $state['workspace']['public_id'] ?? null)
                ->where('slug', V3EngineeringBenchmark::WORKSPACE_SLUG)
                ->with([
                    'activeCorpusGeneration.embeddingSpaceGeneration.embeddingProfile',
                    'activeCorpusGeneration.sparseSpaceGeneration.sparseEmbeddingProfile',
                ])->sole();
            $generation = $workspace->activeCorpusGeneration;
            if (
                $generation === null
                || $generation->public_id !== Exp0007Definition::ACTIVE_GENERATION_PUBLIC_ID
                || $generation->status !== WorkspaceCorpusGenerationStatus::Active
                || $generation->expected_point_count !== 100
                || $generation->point_manifest_digest !== Exp0007Definition::POINT_MANIFEST_DIGEST
                || $generation->embeddingSpaceGeneration->dimensions !== 1024
                || $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint !== Exp0007Definition::EMBEDDING_PROFILE_FINGERPRINT
                || $generation->sparseSpaceGeneration?->sparseEmbeddingProfile->fingerprint !== Exp0007Definition::SPARSE_PROFILE_FINGERPRINT
                || DocumentChunk::query()->where('workspace_id', $workspace->id)->count() !== 100
                || $generation->documentChunks()->count() !== 100
            ) {
                throw new RuntimeException('EXP-0007 active corpus is not the verified 100-point V3 hybrid generation.');
            }

            $policy = EvidenceThresholdPolicy::query()->where('fingerprint', Exp0007Definition::POLICY_FINGERPRINT)->sole();
            Exp0007Definition::assertPolicy($policy);
            $planner = [
                'provider' => (string) config('retrieval.planner.provider'),
                'model' => (string) config('retrieval.planner.model'),
                'contract_schema_version' => (string) config('retrieval.planner.contract_schema_version'),
                'prompt_version' => (string) config('retrieval.planner.prompt_version'),
                'adapter_version' => (string) config('retrieval.planner.adapter_version'),
            ];
            $fingerprintInput = $planner;
            ksort($fingerprintInput);
            $planner['fingerprint'] = hash('sha256', json_encode($fingerprintInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            Exp0007Definition::assertPlanner($planner);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s preflight verified 10 cases, 31 variants, 100 canonical chunks and the active V3 hybrid generation.',
            Exp0007Definition::RUN_ID,
        ));

        return self::SUCCESS;
    }
}
