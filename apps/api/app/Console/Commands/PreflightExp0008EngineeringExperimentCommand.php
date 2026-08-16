<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\DocumentChunk;
use App\Models\EvidenceThresholdPolicy;
use App\Models\Workspace;
use App\Support\Evaluation\BenchmarkCanonicalJson;
use App\Support\Evaluation\Exp0008Definition;
use App\Support\Evaluation\Exp0008EngineeringBenchmark;
use App\Support\Evaluation\Exp0008EngineeringBenchmarkSource;
use App\Support\Evaluation\V3EngineeringBenchmarkState;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class PreflightExp0008EngineeringExperimentCommand extends Command
{
    protected $signature = 'evaluation:benchmark:preflight-exp-0008';

    protected $description = 'Verify EXP-0008 current V3 population and unchanged materialised generation lineage without providers';

    public function handle(Exp0008EngineeringBenchmarkSource $source, V3EngineeringBenchmarkState $states): int
    {
        try {
            $state = $states->read();
            Exp0008Definition::assertProvisioning($state);
            $inputs = $source->load();
            $lineage = Exp0008Definition::lineage();
            if (
                hash_file('sha256', Exp0008EngineeringBenchmark::root().'/population-manifest.json') !== Exp0008Definition::POPULATION_MANIFEST_SHA256
                || hash_file('sha256', Exp0008EngineeringBenchmark::root().'/corpus.json') !== Exp0008Definition::CORPUS_SHA256
                || hash_file('sha256', Exp0008EngineeringBenchmark::root().'/expectations.json') !== Exp0008Definition::EXPECTATIONS_SHA256
                || hash_file('sha256', Exp0008EngineeringBenchmark::root().'/organisation.json') !== Exp0008Definition::ORGANISATION_SHA256
                || hash_file('sha256', Exp0008EngineeringBenchmark::root().'/document-catalog.json') !== Exp0008Definition::CATALOGUE_SHA256
                || hash_file('sha256', Exp0008EngineeringBenchmark::root().'/provisioning-definition.json') !== Exp0008Definition::PROVISIONING_DEFINITION_SHA256
                || ($inputs['manifest']['population_digest'] ?? null) !== $lineage['engineering_population']['digest']
                || ($inputs['manifest']['case_ids_digest'] ?? null) !== Exp0008Definition::CASE_IDS_DIGEST
                || ($inputs['provisioning']['benchmark']['source_catalog_digest'] ?? null) !== Exp0008Definition::SOURCE_CATALOG_DIGEST
            ) {
                throw new RuntimeException('EXP-0008 V3 engineering population lineage is invalid.');
            }
            $variantIdentities = collect($inputs['expectations']['expectations'] ?? [])->map(
                fn (array $expectation): array => [
                    'case_id' => $expectation['case_id'],
                    'variant_id' => $expectation['variant_id'],
                ],
            )->all();
            if (app(BenchmarkCanonicalJson::class)->digest($variantIdentities) !== Exp0008Definition::VARIANT_IDENTITIES_DIGEST) {
                throw new RuntimeException('EXP-0008 V3 engineering variant identities changed.');
            }

            $expectedSources = collect($inputs['provisioning']['documents'] ?? [])->mapWithKeys(
                fn (array $document): array => [(string) $document['document_version_id'] => [
                    'source_path' => $document['source_path'],
                    'source_digest' => $document['source_sha256'],
                ]],
            )->sortKeys()->all();
            $materialisedSources = collect($state['document_versions'] ?? [])->map(
                fn (array $document): array => [
                    'source_path' => $document['source_path'],
                    'source_digest' => $document['source_digest'],
                ],
            )->sortKeys()->all();
            if (count($expectedSources) !== 94 || $expectedSources !== $materialisedSources) {
                throw new RuntimeException('EXP-0008 cannot reuse materialisation because ingestion-bearing source identities changed.');
            }

            $workspace = Workspace::query()
                ->where('public_id', $state['workspace']['public_id'] ?? null)
                ->where('slug', Exp0008EngineeringBenchmark::WORKSPACE_SLUG)
                ->with([
                    'activeCorpusGeneration.embeddingSpaceGeneration.embeddingProfile',
                    'activeCorpusGeneration.sparseSpaceGeneration.sparseEmbeddingProfile',
                ])->sole();
            $generation = $workspace->activeCorpusGeneration;
            if (
                $generation === null
                || $generation->public_id !== Exp0008Definition::ACTIVE_GENERATION_PUBLIC_ID
                || $generation->status !== WorkspaceCorpusGenerationStatus::Active
                || $generation->expected_point_count !== 100
                || $generation->point_manifest_digest !== Exp0008Definition::POINT_MANIFEST_DIGEST
                || $generation->embeddingSpaceGeneration->dimensions !== 1024
                || $generation->embeddingSpaceGeneration->embeddingProfile->fingerprint !== Exp0008Definition::EMBEDDING_PROFILE_FINGERPRINT
                || $generation->sparseSpaceGeneration?->sparseEmbeddingProfile->fingerprint !== Exp0008Definition::SPARSE_PROFILE_FINGERPRINT
                || DocumentChunk::query()->where('workspace_id', $workspace->id)->count() !== 100
                || $generation->documentChunks()->count() !== 100
            ) {
                throw new RuntimeException('EXP-0008 active corpus is not the verified 100-point V3 hybrid generation.');
            }

            $policy = EvidenceThresholdPolicy::query()->where('fingerprint', Exp0008Definition::POLICY_FINGERPRINT)->sole();
            Exp0008Definition::assertPolicy($policy);
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
            Exp0008Definition::assertPlanner($planner);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s preflight verified 10 cases, 31 variants, 94 unchanged sources, 100 canonical chunks and the active V3 hybrid generation.',
            Exp0008Definition::RUN_ID,
        ));

        return self::SUCCESS;
    }
}
