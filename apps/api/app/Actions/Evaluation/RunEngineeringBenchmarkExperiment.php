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
use App\Support\Evaluation\CalExp0002Definition;
use App\Support\Evaluation\CalExp0003Definition;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkExperimentProgress;
use App\Support\Evaluation\EngineeringBenchmarkSource;
use App\Support\Evaluation\EngineeringBenchmarkState;
use App\Support\Evaluation\Exp0004Definition;
use App\Support\Evaluation\Exp0005Definition;
use App\Support\Evaluation\ThresholdCalibrationDefinition;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class RunEngineeringBenchmarkExperiment
{
    public const RUN_ID = 'EXP-0003-post-reliability-corrected-engineering-baseline';

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
        return $this->run($repositoryCommit, $repositoryDirty, self::RUN_ID);
    }

    /** @return array<string, mixed> */
    public function handleExp0004(string $repositoryCommit): array
    {
        return $this->run($repositoryCommit, false, Exp0004Definition::RUN_ID, 'exp0004');
    }

    /** @return array<string, mixed> */
    public function handleExp0005(string $repositoryCommit): array
    {
        return $this->run($repositoryCommit, false, Exp0005Definition::RUN_ID, 'exp0005');
    }

    /** @return array<string, mixed> */
    public function handleThresholdCalibration(string $repositoryCommit): array
    {
        return $this->run(
            $repositoryCommit,
            false,
            ThresholdCalibrationDefinition::RUN_ID,
            'calibration',
        );
    }

    /** @return array<string, mixed> */
    public function handleCalExp0002(string $repositoryCommit): array
    {
        return $this->run(
            $repositoryCommit,
            false,
            CalExp0002Definition::RUN_ID,
            'cal_exp_0002',
        );
    }

    /** @return array<string, mixed> */
    public function handleCalExp0003(string $repositoryCommit): array
    {
        return $this->run(
            $repositoryCommit,
            false,
            CalExp0003Definition::RUN_ID,
            'cal_exp_0003',
        );
    }

    /** @return array<string, mixed> */
    private function run(
        string $repositoryCommit,
        bool $repositoryDirty,
        string $runId,
        string $mode = 'exp0003',
    ): array {
        $this->assertLocalEnvironment($runId);
        if (preg_match('/^[0-9a-f]{40}$/', $repositoryCommit) !== 1) {
            throw new RuntimeException('The experiment requires the exact 40-character repository commit.');
        }
        if ($repositoryDirty) {
            throw new RuntimeException($runId.' requires a clean exact-commit worktree.');
        }
        $state = $this->states->read();
        if (
            ($state['status'] ?? null) !== 'hybrid_verified'
            || ($state['benchmark']['id'] ?? null) !== EngineeringBenchmark::ID
            || ($state['benchmark']['version'] ?? null) !== EngineeringBenchmark::VERSION
            || ($state['benchmark']['digest'] ?? null) !== EngineeringBenchmark::DIGEST
            || ($state['workspace']['slug'] ?? null) !== EngineeringBenchmark::WORKSPACE_SLUG
        ) {
            throw new RuntimeException($runId.' requires the trusted, fully verified V2 hybrid corpus.');
        }
        if ($mode === 'exp0005') {
            Exp0005Definition::assertProvisioning($state);
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
        if (in_array($mode, ['exp0004', 'calibration', 'cal_exp_0002', 'cal_exp_0003'], true)) {
            $policy = $this->policies->handleExp0003Control($scope->activeCorpusGeneration);
            $policy = Exp0004Definition::treatmentPolicy($policy, $this->canonical);
        } else {
            $policy = $this->policies->handle($scope->activeCorpusGeneration);
        }
        if ($mode === 'exp0005') {
            Exp0005Definition::assertPolicy($policy);
        }
        $chunkConfigurations = DocumentChunk::query()
            ->where('workspace_id', $workspace->id)
            ->select(['strategy_name', 'strategy_version', 'configuration', 'configuration_fingerprint'])
            ->get()
            ->unique(fn (DocumentChunk $chunk): string => $chunk->configuration_fingerprint);
        if ($chunkConfigurations->count() !== 1) {
            throw new RuntimeException($runId.' requires one consistent chunking configuration.');
        }
        $chunkConfiguration = $chunkConfigurations->firstOrFail();
        $corpus = match ($mode) {
            'calibration' => $this->source->calibrationCorpus(),
            'cal_exp_0002' => $this->source->calExp0002Corpus(),
            'cal_exp_0003' => $this->source->calExp0003Corpus(),
            default => $this->source->engineeringCorpus(),
        };
        $expectedCases = match ($mode) {
            'calibration' => ThresholdCalibrationDefinition::EXPECTED_CASES,
            'cal_exp_0002' => CalExp0002Definition::EXPECTED_CASES,
            'cal_exp_0003' => CalExp0003Definition::EXPECTED_CASES,
            default => EngineeringBenchmark::EXPECTED_ENGINEERING_CASES,
        };
        $expectedVariants = match ($mode) {
            'calibration' => ThresholdCalibrationDefinition::EXPECTED_VARIANTS,
            'cal_exp_0002' => CalExp0002Definition::EXPECTED_VARIANTS,
            'cal_exp_0003' => CalExp0003Definition::EXPECTED_VARIANTS,
            default => EngineeringBenchmark::EXPECTED_ENGINEERING_VARIANTS,
        };
        $caseIds = $corpus['split']['case_ids'] ?? null;
        if (! is_array($caseIds) || count($caseIds) !== $expectedCases) {
            throw new RuntimeException("The immutable {$corpus['split']['name']} split has an unexpected case count.");
        }
        $cases = collect($corpus['cases'] ?? [])
            ->sortBy(fn (array $case): string => $case['case_id'])->values();
        $variantCount = $cases->sum(fn (array $case): int => count($case['variants'] ?? []));
        if ($cases->count() !== $expectedCases || $variantCount !== $expectedVariants) {
            throw new RuntimeException($runId.' received an unexpected case or variant count.');
        }

        $evaluatedAt = CarbonImmutable::parse(
            match ($mode) {
                'cal_exp_0002' => CalExp0002Definition::EVALUATION_CLOCK,
                'cal_exp_0003' => CalExp0003Definition::EVALUATION_CLOCK,
                default => $corpus['benchmark']['evaluation_clock'],
            },
        );
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
            throw new RuntimeException($runId.' requires explicit planner provider and model lineage.');
        }
        $plannerFingerprintInput = $planner;
        ksort($plannerFingerprintInput);
        $planner['fingerprint'] = hash(
            'sha256',
            json_encode($plannerFingerprintInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        if ($mode === 'cal_exp_0003' && $planner !== CalExp0003Definition::planner()) {
            throw new RuntimeException($runId.' requires the approved structured-chat-v3 planner lineage.');
        }
        if ($mode === 'exp0005' && $planner !== Exp0005Definition::planner()) {
            throw new RuntimeException($runId.' requires the approved ADR-0022-v2 planner lineage.');
        }
        $calibrationDefinition = match ($mode) {
            'cal_exp_0002' => CalExp0002Definition::class,
            'cal_exp_0003' => CalExp0003Definition::class,
            default => null,
        };
        $benchmarkLineage = [
            'id' => EngineeringBenchmark::ID,
            'version' => $calibrationDefinition !== null
                ? $calibrationDefinition::BENCHMARK_VERSION
                : EngineeringBenchmark::VERSION,
            'digest' => $calibrationDefinition !== null
                ? $calibrationDefinition::BENCHMARK_DIGEST
                : $state['benchmark']['digest'],
            'evaluation_clock' => $evaluatedAt->toIso8601String(),
            'split_version' => $corpus['split']['version'],
        ];
        if (in_array($mode, ['calibration', 'cal_exp_0002', 'cal_exp_0003'], true)) {
            $benchmarkLineage += [
                'split_name' => $corpus['split']['name'],
                'calibration_snapshot_digest' => $corpus['snapshot_digest'],
                'calibration_case_ids_digest' => $corpus['split']['case_ids_digest'],
                'calibration_case_ids' => $caseIds,
            ];
        } else {
            $benchmarkLineage += [
                'engineering_snapshot_digest' => $corpus['snapshot_digest'],
                'engineering_case_ids_digest' => $corpus['split']['case_ids_digest'],
                'engineering_case_ids' => $caseIds,
            ];
        }
        $lineage = [
            'repository' => ['commit' => $repositoryCommit, 'dirty' => $repositoryDirty],
            'benchmark' => $benchmarkLineage,
            'mapping' => $mapping,
            'policy' => $policyData,
            'chunking' => $chunking,
            'planner' => $planner,
            'reliability' => [
                'python_retry_owner' => true,
                'embedding' => [
                    'maximum_attempts' => (int) config('retrieval.timeout_budget.embedding_attempts'),
                    'request_timeout_seconds' => (float) config('retrieval.timeout_budget.embedding_request_timeout_seconds'),
                    'initial_backoff_seconds' => (float) config('retrieval.timeout_budget.embedding_initial_backoff_seconds'),
                    'maximum_backoff_seconds' => (float) config('retrieval.timeout_budget.embedding_maximum_backoff_seconds'),
                    'maximum_provider_cooldown_seconds' => (float) config('retrieval.timeout_budget.provider_cooldown_maximum_seconds'),
                ],
                'reranker' => [
                    'maximum_attempts' => (int) config('retrieval.timeout_budget.reranker_attempts_per_side'),
                    'request_timeout_seconds' => (float) config('retrieval.timeout_budget.reranker_request_timeout_seconds'),
                    'initial_backoff_seconds' => (float) config('retrieval.timeout_budget.reranker_initial_backoff_seconds'),
                    'maximum_backoff_seconds' => (float) config('retrieval.timeout_budget.reranker_maximum_backoff_seconds'),
                    'maximum_provider_cooldown_seconds' => (float) config('retrieval.timeout_budget.reranker_provider_cooldown_maximum_seconds'),
                ],
                'shared_voyage_cooldown' => [
                    'scope' => 'python_process',
                    'participants' => ['dense_embedding', 'reranker'],
                    'provider_timing_precedence' => [
                        'retry_after',
                        'rate_limit_reset',
                        'configured_fallback',
                    ],
                ],
                'laravel' => [
                    'timeout_seconds' => (float) config('retrieval.timeout_seconds'),
                    'minimum_timeout_seconds' => (float) config('retrieval.minimum_timeout_seconds'),
                    'outer_maximum_attempts' => (int) config('retrieval.max_attempts'),
                    'typed_provider_rate_limit_replay' => false,
                ],
            ],
            'pricing' => [
                'embedding_cost_per_million_tokens_usd' => (float) config('retrieval.embedding.estimated_cost_per_million_tokens_usd'),
                'embedding_pricing_snapshot' => (string) config('retrieval.embedding.pricing_snapshot'),
                'planner_cost_basis' => 'unavailable',
                'reranker_cost_basis' => 'unavailable',
                'generation' => 'not_executed',
            ],
        ];
        if ($mode === 'exp0004') {
            $lineage['experiment'] = Exp0004Definition::lineage();
        } elseif ($mode === 'exp0005') {
            $lineage['experiment'] = Exp0005Definition::lineage();
        } elseif ($mode === 'calibration') {
            $lineage['experiment'] = ThresholdCalibrationDefinition::lineage();
        } elseif ($mode === 'cal_exp_0002') {
            $lineage['experiment'] = CalExp0002Definition::lineage();
        } elseif ($mode === 'cal_exp_0003') {
            $lineage['experiment'] = CalExp0003Definition::lineage();
        }
        $manifest = $this->progress->initialise($runId, $lineage);
        $lineageDigest = (string) $manifest['lineage_digest'];
        $completed = $this->progress->completedIdentities($runId, $lineageDigest);
        $expectedIdentities = $cases->flatMap(
            fn (array $case) => collect($case['variants'])->map(
                fn (array $variant): array => [
                    'case_id' => $case['case_id'],
                    'variant_id' => $variant['variant_id'],
                ]
            )
        )->all();
        $expectedKeys = collect($expectedIdentities)->map(
            fn (array $identity): string => $identity['case_id'].'::'.$identity['variant_id']
        )->all();
        if (array_diff(array_keys($completed), $expectedKeys) !== []) {
            throw new RuntimeException("Durable {$runId} progress contains an unexpected variant.");
        }
        $resumedCount = count($completed);
        foreach ($cases as $case) {
            foreach ($case['variants'] as $variant) {
                $key = $case['case_id'].'::'.$variant['variant_id'];
                if (isset($completed[$key])) {
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
                            $runId.' stopped because the planner reported a systemic failure: '.$exception->category,
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
                    $runId,
                    $lineageDigest,
                    $case['case_id'],
                    $variant['variant_id'],
                    $observation,
                );
                $completed[$key] = true;
                unset($observation, $pair);
                gc_collect_cycles();
            }
        }
        if (count($completed) !== $expectedVariants) {
            throw new RuntimeException($runId.' did not durably finalise every expected variant.');
        }
        $header = [
            'schema_version' => 'v2',
            'run_id' => $runId,
            'executed_at' => $manifest['started_at'],
            ...$lineage,
        ];
        $path = $this->progress->finaliseFromCheckpoints(
            $runId,
            $lineageDigest,
            $header,
            $expectedIdentities,
        );

        return [
            'path' => $path,
            'case_count' => $cases->count(),
            'variant_count' => $variantCount,
            'resumed_count' => $resumedCount,
        ];
    }

    private function assertLocalEnvironment(string $runId): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException($runId.' is restricted to local/testing environments.');
        }
    }
}
