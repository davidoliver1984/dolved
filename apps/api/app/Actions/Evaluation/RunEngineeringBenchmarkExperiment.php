<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Actions\Retrieval\RetrieveWorkspaceEvidence;
use App\Exceptions\RetrievalPlannerException;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Support\Evaluation\BenchmarkCanonicalJson;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkExperimentProgress;
use App\Support\Evaluation\EngineeringBenchmarkSource;
use App\Support\Evaluation\EngineeringBenchmarkState;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class RunEngineeringBenchmarkExperiment
{
    public const RUN_ID = 'EXP-0002-adr0022-corrected-planning-baseline';

    public function __construct(
        private EngineeringBenchmarkSource $source,
        private EngineeringBenchmarkState $states,
        private ResolveEngineeringBenchmarkPolicy $policies,
        private BuildAuthorisedKnowledgeScope $authorisation,
        private RetrieveWorkspaceEvidence $retrieval,
        private BenchmarkCanonicalJson $canonical,
        private EngineeringBenchmarkExperimentProgress $progress,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $repositoryCommit, bool $repositoryDirty): array
    {
        $this->assertLocalEnvironment();
        if (preg_match('/^[0-9a-f]{40}$/', $repositoryCommit) !== 1) {
            throw new RuntimeException('The experiment requires the exact 40-character repository commit.');
        }
        if ($repositoryDirty) {
            throw new RuntimeException('EXP-0002 requires a clean exact-commit worktree.');
        }
        $state = $this->states->read();
        if (
            ($state['status'] ?? null) !== 'hybrid_verified'
            || ($state['benchmark']['version'] ?? null) !== EngineeringBenchmark::VERSION
            || ($state['workspace']['slug'] ?? null) !== EngineeringBenchmark::WORKSPACE_SLUG
        ) {
            throw new RuntimeException('EXP-0002 requires the trusted, fully verified V2 hybrid corpus.');
        }
        $workspace = Workspace::query()
            ->where('public_id', $state['workspace']['public_id'])
            ->where('slug', EngineeringBenchmark::WORKSPACE_SLUG)
            ->sole();
        $owner = User::query()->whereKey($state['owner']['id'])->sole();
        $scope = $this->authorisation->handle($owner, $workspace->public_id);
        if ($scope->activeCorpusGeneration === null) {
            throw new RuntimeException('The benchmark workspace has no active corpus generation.');
        }
        $policy = $this->policies->handle($scope->activeCorpusGeneration);
        $chunkConfigurations = DocumentChunk::query()
            ->where('workspace_id', $workspace->id)
            ->select(['strategy_name', 'strategy_version', 'configuration', 'configuration_fingerprint'])
            ->get()
            ->unique(fn (DocumentChunk $chunk): string => $chunk->configuration_fingerprint);
        if ($chunkConfigurations->count() !== 1) {
            throw new RuntimeException('EXP-0002 requires one consistent chunking configuration.');
        }
        $chunkConfiguration = $chunkConfigurations->firstOrFail();
        $benchmark = $this->source->load();
        $compiled = $this->source->compiledCorpus();
        $engineeringIds = $benchmark['split']['assignments']['engineering_tuning'] ?? null;
        if (! is_array($engineeringIds) || count($engineeringIds) !== EngineeringBenchmark::EXPECTED_ENGINEERING_CASES) {
            throw new RuntimeException('The immutable engineering split does not contain exactly 42 cases.');
        }
        $cases = collect($compiled['cases'] ?? [])->filter(
            fn (mixed $case): bool => is_array($case)
                && in_array($case['case_id'] ?? null, $engineeringIds, true),
        )->sortBy(fn (array $case): string => $case['case_id'])->values();
        $variantCount = $cases->sum(fn (array $case): int => count($case['variants'] ?? []));
        if ($cases->count() !== 42 || $variantCount !== 126) {
            throw new RuntimeException('EXP-0002 is restricted to exactly 42 engineering cases and 126 variants.');
        }

        $evaluatedAt = CarbonImmutable::parse($compiled['evaluation_clock']);
        $mapping = [
            'schema_version' => 'v1',
            'benchmark' => $state['benchmark'],
            'workspace' => $state['workspace'],
            'locations' => $state['locations'],
            'document_families' => $state['document_families'],
            'document_versions' => $state['document_versions'],
            'generations' => $state['generations'],
            'provisioning_mapping_digest' => $state['mapping_digest'],
        ];
        $mapping += ['snapshot_digest' => $this->canonical->digest($mapping)];
        $policyData = $policy->only($policy->getFillable()) + [
            'public_id' => $policy->public_id,
            'fingerprint' => $policy->fingerprint,
        ];
        $chunking = [
            'strategy_name' => $chunkConfiguration->strategy_name,
            'strategy_version' => $chunkConfiguration->strategy_version,
            'configuration' => $chunkConfiguration->configuration,
            'configuration_fingerprint' => $chunkConfiguration->configuration_fingerprint,
        ];
        $planner = [
            'provider' => (string) config('retrieval.planner.provider'),
            'model' => (string) config('retrieval.planner.model'),
            'contract_schema_version' => (string) config('retrieval.planner.contract_schema_version'),
            'prompt_version' => (string) config('retrieval.planner.prompt_version'),
            'adapter_version' => (string) config('retrieval.planner.adapter_version'),
        ];
        if ($planner['provider'] === '' || $planner['model'] === '') {
            throw new RuntimeException('EXP-0002 requires explicit planner provider and model lineage.');
        }
        $plannerFingerprintInput = $planner;
        ksort($plannerFingerprintInput);
        $planner['fingerprint'] = hash(
            'sha256',
            json_encode($plannerFingerprintInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $lineage = [
            'repository' => ['commit' => $repositoryCommit, 'dirty' => $repositoryDirty],
            'benchmark' => [
                'id' => EngineeringBenchmark::ID,
                'version' => EngineeringBenchmark::VERSION,
                'digest' => $state['benchmark']['digest'],
                'evaluation_clock' => $evaluatedAt->toIso8601String(),
                'split_version' => $benchmark['split']['split_version'],
                'engineering_case_ids' => $engineeringIds,
            ],
            'mapping' => $mapping,
            'policy' => $policyData,
            'chunking' => $chunking,
            'planner' => $planner,
            'pricing' => [
                'embedding_cost_per_million_tokens_usd' => (float) config('retrieval.embedding.estimated_cost_per_million_tokens_usd'),
                'embedding_pricing_snapshot' => (string) config('retrieval.embedding.pricing_snapshot'),
                'planner_cost_basis' => 'unavailable',
                'reranker_cost_basis' => 'unavailable',
                'generation' => 'not_executed',
            ],
        ];
        $manifest = $this->progress->initialise(self::RUN_ID, $lineage);
        $lineageDigest = (string) $manifest['lineage_digest'];
        $observations = $this->progress->observations(self::RUN_ID, $lineageDigest);
        $expectedKeys = $cases->flatMap(
            fn (array $case) => collect($case['variants'])->map(
                fn (array $variant): string => $case['case_id'].'::'.$variant['variant_id']
            )
        )->all();
        if (array_diff(array_keys($observations), $expectedKeys) !== []) {
            throw new RuntimeException('Durable EXP-0002 progress contains an unexpected variant.');
        }
        $resumedCount = count($observations);
        foreach ($cases as $case) {
            foreach ($case['variants'] as $variant) {
                $key = $case['case_id'].'::'.$variant['variant_id'];
                if (isset($observations[$key])) {
                    continue;
                }
                $started = hrtime(true);
                try {
                    $pair = $this->retrieval->handlePairForLocalEvaluation(
                        $scope,
                        $variant['question'],
                        $policy->dense_candidate_k,
                        $evaluatedAt,
                        $policy,
                    );
                    $observation = [
                        'case' => $case,
                        'variant' => $variant,
                        'latency_ms' => (hrtime(true) - $started) / 1_000_000,
                        'observed_at' => now()->toIso8601String(),
                        'planning' => [
                            'status' => 'succeeded',
                            'provider' => $planner['provider'],
                            'model' => $planner['model'],
                            'attempt_count' => 1,
                        ],
                        'retrieval_executed' => true,
                        'dense' => [
                            'result' => $pair['dense']['result']->toArray(),
                            'trace' => $pair['dense']['trace'],
                        ],
                        'hybrid' => [
                            'result' => $pair['hybrid']['result']->toArray(),
                            'trace' => $pair['hybrid']['trace'],
                        ],
                    ];
                } catch (RetrievalPlannerException $exception) {
                    if ($exception->systemic) {
                        throw new RuntimeException(
                            'EXP-0002 stopped because the planner reported a systemic failure: '.$exception->category,
                            0,
                            $exception,
                        );
                    }
                    $observation = [
                        'case' => $case,
                        'variant' => $variant,
                        'latency_ms' => (hrtime(true) - $started) / 1_000_000,
                        'observed_at' => now()->toIso8601String(),
                        'planning' => [
                            'status' => 'failed',
                            'provider' => $planner['provider'],
                            'model' => $planner['model'],
                            'failure_category' => $exception->category,
                            'provider_status' => $exception->providerStatus,
                            'attempt_count' => $exception->attemptCount,
                        ],
                        'retrieval_executed' => false,
                        'dense' => null,
                        'hybrid' => null,
                    ];
                }
                $this->progress->writeObservation(
                    self::RUN_ID,
                    $lineageDigest,
                    $case['case_id'],
                    $variant['variant_id'],
                    $observation,
                );
                $observations[$key] = $observation;
            }
        }
        if (count($observations) !== EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS) {
            throw new RuntimeException('EXP-0002 did not durably finalise all engineering variants.');
        }
        $payload = [
            'schema_version' => 'v2',
            'run_id' => self::RUN_ID,
            'executed_at' => $manifest['started_at'],
            ...$lineage,
            'observations' => collect($expectedKeys)->map(fn (string $key): array => $observations[$key])->all(),
        ];
        $path = $this->progress->finalise(self::RUN_ID, $payload);

        return [
            'path' => $path,
            'case_count' => $cases->count(),
            'variant_count' => $variantCount,
            'resumed_count' => $resumedCount,
        ];
    }

    private function assertLocalEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('EXP-0002 is restricted to local/testing environments.');
        }
    }
}
