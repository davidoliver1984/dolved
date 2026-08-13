<?php

declare(strict_types=1);

namespace App\Actions\Retrieval;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Enums\RetrievalOutcome;
use App\Exceptions\RetrievalException;
use App\Exceptions\RetrievalExecutionException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EvidenceThresholdPolicy;
use App\Services\Retrieval\EligibilityResolver;
use App\Services\Retrieval\ResolveEvidenceThresholdPolicy;
use App\Services\Retrieval\RetrievalClient;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\RetrievalPlan;
use App\Support\Retrieval\RetrievalResult;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class RetrieveWorkspaceEvidence
{
    public function __construct(
        private RetrievalClient $client,
        private EligibilityResolver $eligibility,
        private ResolveEvidenceThresholdPolicy $policies,
        private DocumentAuthorityTimeline $timeline,
    ) {}

    public function handle(
        AuthorisedKnowledgeScope $authorised,
        string $question,
        int $candidateK,
    ): RetrievalResult {
        return $this->execute(
            $authorised,
            $question,
            $candidateK,
            CarbonImmutable::now(),
        );
    }

    /**
     * @return array{result: RetrievalResult, trace: array<string, mixed>}
     */
    public function handleForLocalEvaluation(
        AuthorisedKnowledgeScope $authorised,
        string $question,
        int $candidateK,
        CarbonImmutable $evaluatedAt,
        ?EvidenceThresholdPolicy $calibratingPolicy,
        bool $denseOnly,
    ): array {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Observed benchmark retrieval is restricted to local/testing environments.');
        }
        if (
            ! $denseOnly
            && $calibratingPolicy?->status !== EvidenceThresholdPolicyStatus::Calibrating
        ) {
            throw new RuntimeException('Observed hybrid retrieval requires an explicitly CALIBRATING policy.');
        }
        $trace = [];
        $result = $this->execute(
            $authorised,
            $question,
            $candidateK,
            $evaluatedAt,
            $calibratingPolicy,
            $denseOnly,
            function (string $stage, mixed $value) use (&$trace): void {
                $trace[$stage] = $value;
            },
        );

        return ['result' => $result, 'trace' => $trace];
    }

    /**
     * Run the dense and hybrid benchmark arms from one provider-produced plan.
     *
     * @return array{dense: array{result: RetrievalResult, trace: array<string, mixed>}, hybrid: array{result: RetrievalResult, trace: array<string, mixed>}}
     */
    public function handlePairForLocalEvaluation(
        AuthorisedKnowledgeScope $authorised,
        string $question,
        int $denseCandidateK,
        CarbonImmutable $evaluatedAt,
        EvidenceThresholdPolicy $calibratingPolicy,
    ): array {
        $this->assertLocalEvaluationPolicy($calibratingPolicy);
        $plan = $this->client->plan($authorised->workspace, $question, $evaluatedAt);

        return [
            'dense' => $this->executeObserved(
                $authorised,
                $question,
                $denseCandidateK,
                $evaluatedAt,
                null,
                true,
                $plan,
            ),
            'hybrid' => $this->executeObserved(
                $authorised,
                $question,
                $denseCandidateK,
                $evaluatedAt,
                $calibratingPolicy,
                false,
                $plan,
            ),
        ];
    }

    private function assertLocalEvaluationPolicy(EvidenceThresholdPolicy $policy): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Observed benchmark retrieval is restricted to local/testing environments.');
        }
        if ($policy->status !== EvidenceThresholdPolicyStatus::Calibrating) {
            throw new RuntimeException('Observed hybrid retrieval requires an explicitly CALIBRATING policy.');
        }
    }

    /**
     * @return array{result: RetrievalResult, trace: array<string, mixed>}
     */
    private function executeObserved(
        AuthorisedKnowledgeScope $authorised,
        string $question,
        int $candidateK,
        CarbonImmutable $evaluatedAt,
        ?EvidenceThresholdPolicy $policy,
        bool $denseOnly,
        RetrievalPlan $plan,
    ): array {
        $trace = [];
        $result = $this->execute(
            $authorised,
            $question,
            $candidateK,
            $evaluatedAt,
            $policy,
            $denseOnly,
            function (string $stage, mixed $value) use (&$trace): void {
                $trace[$stage] = $value;
            },
            $plan,
        );

        return ['result' => $result, 'trace' => $trace];
    }

    /** @param null|callable(string, mixed): void $observe */
    private function execute(
        AuthorisedKnowledgeScope $authorised,
        string $question,
        int $candidateK,
        CarbonImmutable $evaluatedAt,
        ?EvidenceThresholdPolicy $policyOverride = null,
        bool $denseOnly = false,
        ?callable $observe = null,
        ?RetrievalPlan $planOverride = null,
    ): RetrievalResult {
        $currentStage = 'transport_orchestration';
        try {
            $plan = $planOverride ?? $this->client->plan($authorised->workspace, $question, $evaluatedAt);
            $observe?->__invoke('plan', [
                'query' => $plan->query,
                'temporal_mode' => $plan->temporalMode->value,
                'explicit_date' => $plan->explicitDate?->toDateString(),
                'temporal_reference' => $plan->temporalReference === null ? null : [
                    'kind' => $plan->temporalReference['kind']->value,
                    'value' => $plan->temporalReference['value'],
                ],
                'location_references' => $plan->locationReferences,
                'clarification_reason' => $plan->clarificationReason?->value,
                'classifier_lineage' => $plan->classifierLineage->toArray(),
                'usage' => [$plan->classifierUsage->toArray()],
            ]);
            $eligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            $observe?->__invoke('eligibility', [
                'outcome' => $eligible->outcome->value,
                'document_public_ids_by_side' => $eligible->documentPublicIdsBySide,
                'reason' => $eligible->reason,
                'resolved_location_public_id' => $eligible->resolvedLocationPublicId,
            ]);
            if (! $eligible->canSearch()) {
                return new RetrievalResult($eligible->outcome, reason: $eligible->reason);
            }
            $corpus = $authorised->activeCorpusGeneration;
            if ($corpus === null) {
                return new RetrievalResult(RetrievalOutcome::NoEligibleEvidence);
            }
            $policy = $denseOnly || $corpus->sparse_space_generation_id === null
                ? null
                : ($policyOverride ?? $this->policies->handle($corpus));
            $currentStage = 'transport_orchestration';
            $search = $this->client->search(
                $authorised->workspace,
                $corpus,
                $eligible,
                $plan->query,
                $candidateK,
                $policy,
                $denseOnly,
                $observe !== null,
            );
            $diagnostics = $observe === null
                ? []
                : $this->hydrateDiagnostics(
                    $search->diagnostics,
                    $eligible->documentPublicIdsBySide,
                    $authorised,
                    $denseOnly,
                );
            $observe?->__invoke('search', [
                'candidates' => $search->candidates,
                'lineage' => $search->lineage,
                'diagnostics' => $diagnostics,
                'usage' => $search->usage,
            ]);
            $candidates = $search->candidates;
            if ($candidates === []) {
                return new RetrievalResult(RetrievalOutcome::NoRetrievalCandidates);
            }
            if ($policy !== null) {
                $this->policies->assertSearchLineage($policy, $search->lineage);
                if (
                    ($search->lineage['sparse_space_generation_id'] ?? null)
                    !== $corpus->sparseSpaceGeneration?->public_id
                ) {
                    throw new RetrievalException('Sparse generation lineage does not match the active corpus.');
                }
            }

            $currentStage = 'final_eligibility';
            $freshEligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            $hydrated = $this->hydrateAndRecheck(
                $candidates,
                $freshEligible->canSearch()
                    ? $freshEligible->documentPublicIdsBySide
                    : [],
                $authorised,
                $denseOnly,
            );
            $observe?->__invoke('hydrated', $hydrated);

            if ($hydrated === []) {
                return new RetrievalResult(RetrievalOutcome::NoRetrievalCandidates);
            }
            if ($policy === null) {
                return new RetrievalResult(RetrievalOutcome::EvidenceFound, $hydrated);
            }

            $currentStage = 'reranker';
            $reranked = $this->client->rerank(
                $authorised->workspace,
                $plan->query,
                $hydrated,
                $policy,
            );
            $observe?->__invoke('reranked', $reranked);
            $this->policies->assertRerankerLineage($policy, $reranked['profile']);
            $rerankedByChunk = collect($reranked['candidates'])->keyBy(
                fn (array $candidate): string => $candidate['side'].':'.$candidate['chunk_id']
            );
            $candidateByChunk = collect($candidates)->keyBy(
                fn (array $candidate): string => $candidate['side'].':'.$candidate['chunk_id']
            );
            $rerankCandidates = collect($reranked['candidates'])->map(
                fn (array $rerankedCandidate): ?array => $candidateByChunk->get(
                    $rerankedCandidate['side'].':'.$rerankedCandidate['chunk_id']
                )
            )->filter()->values()->all();
            $currentStage = 'final_eligibility';
            $finalEligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            $final = $this->hydrateAndRecheck(
                $rerankCandidates,
                $finalEligible->canSearch() ? $finalEligible->documentPublicIdsBySide : [],
                $authorised,
                $denseOnly,
            );
            $currentStage = 'threshold';
            $qualified = collect($final)->map(function (array $candidate) use ($rerankedByChunk, $policy): ?array {
                $rerankedCandidate = $rerankedByChunk->get(
                    $candidate['side'].':'.$candidate['chunk_id']
                );
                if (! is_array($rerankedCandidate) || (float) $rerankedCandidate['score'] < $policy->evidence_threshold) {
                    return null;
                }

                return [
                    ...$candidate,
                    'fused_score' => $candidate['score'],
                    'fused_rank' => $candidate['rank'],
                    'score' => (float) $rerankedCandidate['score'],
                    'rank' => $rerankedCandidate['rank'],
                    'evidence_threshold_policy_version' => $policy->version,
                    'evidence_threshold_policy_fingerprint' => $policy->fingerprint,
                    'reranker_provider' => $policy->reranker_provider,
                    'reranker_model' => $policy->reranker_model,
                    'reranker_adapter_version' => $policy->reranker_adapter_version,
                ];
            })->filter()->sortBy('rank')->values();
            $observe?->__invoke('threshold_qualified', $qualified->all());
            if ($qualified->isEmpty()) {
                return new RetrievalResult(RetrievalOutcome::InsufficientEvidence);
            }

            $sides = array_keys($eligible->documentPublicIdsBySide);
            if (count($sides) === 2 && collect($sides)->contains(
                fn (string $side): bool => ! $qualified->contains(
                    fn (array $candidate): bool => $candidate['side'] === $side
                )
            )) {
                return new RetrievalResult(RetrievalOutcome::ComparisonScopeIncomplete);
            }
            $accepted = $qualified->groupBy('side')->flatMap(
                fn ($sideCandidates) => $sideCandidates->take($policy->final_evidence_k)
            )->sortBy('rank')->values()->all();
            $observe?->__invoke('final_evidence', $accepted);

            return new RetrievalResult(RetrievalOutcome::EvidenceFound, $accepted);
        } catch (RetrievalException $exception) {
            $failure = $exception instanceof RetrievalExecutionException
                ? $exception->observation->toArray()
                : [
                    'stage' => $currentStage,
                    'execution' => 'orchestration',
                    'provider' => null,
                    'model' => null,
                    'category' => 'unknown',
                    'http_status' => null,
                    'retry_count' => null,
                    'request_count' => null,
                    'latency_ms' => 0.0,
                    'usage' => [],
                    'downstream_request_attempted' => false,
                    'candidate_lineage_produced' => isset($search) && $search->candidates !== [],
                ];
            $observe?->__invoke('failure', $failure);

            return new RetrievalResult(RetrievalOutcome::RetrievalFailed);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, list<string>>  $eligibleBySide
     * @return list<array<string, mixed>>
     */
    private function hydrateAndRecheck(
        array $candidates,
        array $eligibleBySide,
        AuthorisedKnowledgeScope $authorised,
        bool $denseOnly = false,
    ): array {
        $chunkIds = collect($candidates)
            ->pluck('chunk_id')
            ->filter(fn (mixed $value): bool => is_string($value))
            ->unique()
            ->values();
        $chunks = DocumentChunk::query()
            ->where('workspace_id', $authorised->workspace->id)
            ->whereIn('public_id', $chunkIds)
            ->with(['document.family', 'document.createdBy'])
            ->get()
            ->keyBy('public_id');
        $generationId = $authorised->activeCorpusGeneration?->id;
        $result = [];
        foreach ($candidates as $candidate) {
            $side = $candidate['side'] ?? null;
            $chunkId = $candidate['chunk_id'] ?? null;
            $documentId = $candidate['document_id'] ?? null;
            if (! is_string($side) || ! is_string($chunkId) || ! is_string($documentId)) {
                continue;
            }
            $chunk = $chunks->get($chunkId);
            if (! $chunk instanceof DocumentChunk || $chunk->document->public_id !== $documentId) {
                continue;
            }
            if (! in_array($documentId, $eligibleBySide[$side] ?? [], true)) {
                continue;
            }
            if (
                $candidate['workspace_corpus_generation_id']
                    !== $authorised->activeCorpusGeneration?->public_id
                || $candidate['embedding_space_generation_id']
                    !== $authorised->activeCorpusGeneration?->embeddingSpaceGeneration->public_id
            ) {
                continue;
            }
            $expectedSparse = $authorised->activeCorpusGeneration?->sparseSpaceGeneration?->public_id;
            if (($candidate['sparse_space_generation_id'] ?? null) !== $expectedSparse) {
                continue;
            }
            if ($generationId === null || ! $chunk->workspaceCorpusGenerations()->whereKey($generationId)->exists()) {
                continue;
            }
            $result[] = $this->candidate($candidate, $chunk);
        }

        return $result;
    }

    /** @param array<string, mixed> $candidate @return array<string, mixed> */
    private function candidate(array $candidate, DocumentChunk $chunk): array
    {
        $document = $chunk->document;
        $lineage = $this->timeline->attainedVersions($document->family);
        $position = $lineage->search(fn (Document $item): bool => $item->is($document));

        return [
            'chunk_id' => $chunk->public_id,
            'document_id' => $document->public_id,
            'document_family_id' => $document->family->public_id,
            'version_position' => is_int($position) ? $position + 1 : null,
            'source_filename' => $document->source_filename,
            'chunk_text' => $chunk->text,
            'provenance' => $chunk->provenance,
            'score' => $candidate['score'],
            'rank' => $candidate['rank'],
            'retrieval_method' => $candidate['retrieval_method'],
            'side' => $candidate['side'],
            'workspace_corpus_generation_id' => $candidate['workspace_corpus_generation_id'],
            'embedding_space_generation_id' => $candidate['embedding_space_generation_id'],
            'sparse_space_generation_id' => $candidate['sparse_space_generation_id'] ?? null,
            'dense_score' => $candidate['dense_score'] ?? null,
            'dense_rank' => $candidate['dense_rank'] ?? null,
            'sparse_score' => $candidate['sparse_score'] ?? null,
            'sparse_rank' => $candidate['sparse_rank'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $diagnostics
     * @param  array<string, list<string>>  $eligibleBySide
     * @return list<array<string, mixed>>
     */
    private function hydrateDiagnostics(
        array $diagnostics,
        array $eligibleBySide,
        AuthorisedKnowledgeScope $authorised,
        bool $denseOnly,
    ): array {
        return collect($diagnostics)->map(function (mixed $diagnostic) use (
            $eligibleBySide,
            $authorised,
            $denseOnly,
        ): ?array {
            if (! is_array($diagnostic) || ! is_string($diagnostic['side'] ?? null)) {
                return null;
            }

            $result = ['side' => $diagnostic['side']];
            foreach (['dense_candidates', 'sparse_candidates', 'fused_candidates'] as $stage) {
                $candidates = $diagnostic[$stage] ?? null;
                $result[$stage] = is_array($candidates)
                    ? $this->hydrateAndRecheck($candidates, $eligibleBySide, $authorised, $denseOnly)
                    : null;
            }

            return $result;
        })->filter()->values()->all();
    }
}
