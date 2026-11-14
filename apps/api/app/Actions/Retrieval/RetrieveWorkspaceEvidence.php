<?php

declare(strict_types=1);

namespace App\Actions\Retrieval;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Enums\RequestedEvidenceType;
use App\Enums\RetrievalClarificationSource;
use App\Enums\RetrievalOutcome;
use App\Enums\RetrievalTemporalMode;
use App\Exceptions\RetrievalException;
use App\Exceptions\RetrievalExecutionException;
use App\Exceptions\TopicalFamilySelectionRejected;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentExtractionProjectionElement;
use App\Models\EvidenceThresholdPolicy;
use App\Services\Retrieval\EligibilityResolver;
use App\Services\Retrieval\ResolveEvidenceThresholdPolicy;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Retrieval\TemporalEvidenceAlternatives;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\ApplicabilityScopeFact;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\EligibleRetrievalScope;
use App\Support\Retrieval\EvidenceNeedsPlan;
use App\Support\Retrieval\ExplicitTemporalReferences;
use App\Support\Retrieval\NeedsEvidenceSelection;
use App\Support\Retrieval\RetrievalPlan;
use App\Support\Retrieval\RetrievalResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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
        ?callable $observe = null,
    ): RetrievalResult {
        return $this->execute(
            $authorised,
            $question,
            $candidateK,
            CarbonImmutable::now(),
            observe: $observe,
        );
    }

    /** Uses the same search, fusion, reranker and final eligibility checks for each need. */
    public function handleNeeds(
        AuthorisedKnowledgeScope $authorised,
        EvidenceNeedsPlan $plan,
        int $candidateK,
        CarbonImmutable $evaluatedAt,
        ?callable $observe = null,
        ?CarbonImmutable $deadline = null,
    ): RetrievalResult {
        $needs = $plan->executionNeeds();
        $usage = [$plan->usage->toArray()];
        $observe?->__invoke('needs_plan', $plan->value);
        if ($plan->value['decision'] === 'clarify') {
            return new RetrievalResult(RetrievalOutcome::ClarificationRequired,
                reason: 'evidence_needs_clarification',
                clarificationSource: RetrievalClarificationSource::Planner, usage: $usage,
                clarificationQuestion: $plan->value['clarification']);
        }
        if (count($needs) > NeedsEvidenceSelection::MAX_NEEDS
            || NeedsEvidenceSelection::searchLanes($needs) > NeedsEvidenceSelection::MAX_SEARCH_LANES) {
            return new RetrievalResult(RetrievalOutcome::ClarificationRequired,
                reason: 'evidence_needs_capacity',
                clarificationSource: RetrievalClarificationSource::Planner, usage: $usage,
                clarificationQuestion: 'I can handle up to three requested aspects and four searches at once; a version comparison uses two searches. Which parts would you like me to address first?');
        }
        $results = [];
        $plans = [];
        $scopeFacts = [];
        $topicalCurrent = [];
        $discoveryWork = [];
        foreach ($needs as $need) {
            $id = $need['need_id'];
            $plans[$id] = $plan->retrievalPlan($id);
            if ($need['operation'] === 'scope_enquiry') {
                $fact = $this->eligibility->scopeFact($authorised, $plans[$id], $evaluatedAt);
                if (! $fact instanceof ApplicabilityScopeFact) {
                    $observe?->__invoke('need.'.$id.'.scope_fact', ['need_id' => $id, 'result' => 'unresolved',
                        'reason' => $fact->reason ?? 'scope_record_unavailable']);

                    if ($fact->outcome === RetrievalOutcome::NoEligibleEvidence) {
                        return new RetrievalResult(RetrievalOutcome::NoEligibleEvidence,
                            reason: $fact->reason ?? 'scope_record_unavailable', usage: $usage);
                    }

                    return new RetrievalResult(RetrievalOutcome::ClarificationRequired,
                        reason: $fact->reason ?? 'scope_record_unavailable',
                        clarificationSource: RetrievalClarificationSource::EligibilityResolver,
                        usage: $usage, clarificationQuestion: 'Please identify the policy and location whose recorded scope you want to check.');
                }
                $scopeFacts[$id] = $fact;
                $observe?->__invoke('need.'.$id.'.scope_fact', $fact->value);
                $results[$id] = new RetrievalResult(RetrievalOutcome::EvidenceFound);

                continue;
            }
            $c2 = $need['operation'] === 'retrieve' && ExplicitTemporalReferences::forNeed($need) !== [];
            if (($need['operation'] === 'compare' || $c2)
                && ($plans[$id]->evidenceNeed['subject_binding']['subject_status'] ?? null) === 'topic') {
                $discovery = [];
                try {
                    $topical = $this->selectTopicalComparisonFamily($authorised, $plans[$id],
                        $candidateK, $evaluatedAt, $discovery, $c2);
                } catch (RetrievalException $exception) {
                    $failure = $exception instanceof RetrievalExecutionException
                        ? $exception->observation->toArray()
                        : ['stage' => 'topical_family_selection', 'category' => 'contract_failure',
                            'request_count' => null, 'usage' => []];
                    $observe?->__invoke('need.'.$id.'.topical_family_selection', [
                        'decision' => 'failed', 'family_id' => null, 'chunk_id' => null,
                        'failure' => $failure,
                    ]);

                    if ($c2 && ($exception instanceof TopicalFamilySelectionRejected || $this->optionalDiscoveryUnavailable($exception))) {
                        $rejected = $exception instanceof TopicalFamilySelectionRejected;
                        $topical = ['decision' => $rejected ? 'no_match' : 'unavailable', 'original_decision' => $rejected ? 'selected' : null,
                            'validation_rejection_code' => $rejected ? $exception->rejectionCode : null,
                            'candidate_identities' => $rejected ? $exception->candidateIdentities : [],
                            'family_id' => null, 'chunk_id' => null,
                            'usage' => [...($discovery['completed_usage'] ?? []), ...($rejected ? [$exception->usage] : ($failure['usage'] ?? []))],
                            'diagnostics' => [...$discovery, 'status' => $rejected ? 'unresolved' : 'unavailable', 'failure' => $failure]];
                    } else {
                        return new RetrievalResult(RetrievalOutcome::RetrievalFailed,
                            reason: 'topical_family_review_failed',
                            lineage: ['failure' => $failure, 'topical_discovery_work' => [$id => $discovery]],
                            usage: [...$usage, ...($c2 ? ($discovery['completed_usage'] ?? []) : []), ...(is_array($failure['usage'] ?? null) ? $failure['usage'] : [])]);
                    }
                }
                $usage = [...$usage, ...$topical['usage']];
                $discoveryWork[$id] = [...$topical['diagnostics'], 'original_decision' => $topical['original_decision'] ?? null,
                    'effective_decision' => $topical['decision'], 'validation_rejection_code' => $topical['validation_rejection_code'] ?? null];
                $observe?->__invoke('need.'.$id.'.topical_family_selection', [
                    'decision' => $topical['decision'],
                    'original_decision' => $topical['original_decision'] ?? null,
                    'validation_rejection_code' => $topical['validation_rejection_code'] ?? null,
                    'candidate_identities' => $topical['candidate_identities'] ?? [],
                    'family_id' => $topical['family_id'],
                    'chunk_id' => $topical['chunk_id'],
                    'usage' => $topical['usage'],
                    'diagnostics' => $topical['diagnostics'],
                ]);
                if ($topical['family_id'] === null) {
                    if (! $c2) {
                        $results[$id] = new RetrievalResult(RetrievalOutcome::ComparisonScopeIncomplete, reason: 'topic_family_unresolved');

                        continue;
                    }
                    $guard = ['status' => 'not_run', 'reason' => ($topical['diagnostics']['status'] ?? null) === 'unavailable'
                        ? 'discovery_unavailable' : 'discovery_unresolved', 'fallback' => 'original_unbound'];
                    $discoveryWork[$id]['temporal_guard'] = $guard;
                    $observe?->__invoke('need.'.$id.'.temporal_guard', $guard);
                    if ($deadline !== null && CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
                        $discoveryWork[$id]['fallback_blocked'] = 'deadline_exhausted';

                        return new RetrievalResult(RetrievalOutcome::RetrievalFailed, reason: 'deadline_exhausted',
                            lineage: ['topical_discovery_work' => $discoveryWork], usage: $usage);
                    }
                } else {
                    $plans[$id] = $this->comparisonPlanForFamily($plans[$id], $topical['family_id']);
                }
                if ($c2 && $topical['family_id'] !== null) {
                    $topicalCurrent[$id] = $this->reuseTopicalCurrent($authorised, $plans[$id], $topical, $evaluatedAt);
                    $discoveryWork[$id]['current_reuse'] = $topicalCurrent[$id]->reason ?? 'equivalent';
                    $observe?->__invoke('need.'.$id.'.topical_current_reuse', $topicalCurrent[$id]->toArray());
                    if ($plans[$id]->temporalMode === RetrievalTemporalMode::Current) {
                        $results[$id] = $topicalCurrent[$id];

                        continue;
                    }
                }
            }
            $results[$id] = $this->execute($authorised, $need['aspect'], $candidateK, $evaluatedAt,
                observe: $observe === null ? null : fn ($stage, $value) => $observe('need.'.$id.'.'.$stage, $value),
                planOverride: $plans[$id], recordPlannerUsage: false);
            $usage = [...$usage, ...$results[$id]->usage];
            if ($results[$id]->outcome === RetrievalOutcome::RetrievalFailed) {
                return new RetrievalResult(RetrievalOutcome::RetrievalFailed, reason: $results[$id]->reason,
                    lineage: [...$results[$id]->lineage, 'topical_discovery_work' => $discoveryWork], usage: $usage);
            }
            if ($results[$id]->outcome === RetrievalOutcome::ClarificationRequired) {
                return new RetrievalResult(RetrievalOutcome::ClarificationRequired,
                    reason: $results[$id]->reason, clarificationSource: $results[$id]->clarificationSource,
                    lineage: [...$results[$id]->lineage, 'topical_discovery_work' => $discoveryWork],
                    usage: $usage, clarificationQuestion: $results[$id]->clarificationQuestion);
            }
        }
        $temporal = app(TemporalEvidenceAlternatives::class)->augment($authorised, $needs, $plans, $results,
            $evaluatedAt, fn ($dated, $id, $target) => $dated->temporalMode === RetrievalTemporalMode::Current && isset($topicalCurrent[$id])
                ? $topicalCurrent[$id]
                : $this->execute($authorised, $dated->query, $candidateK, $evaluatedAt,
                    observe: $observe === null ? null : fn ($stage, $value) => $observe('need.'.$id.'.'.$target.'.'.$stage, $value),
                    planOverride: $dated, recordPlannerUsage: false), $observe);
        $usage = [...$usage, ...$temporal['usage']];
        if (isset($temporal['failure'])) {
            return new RetrievalResult(RetrievalOutcome::RetrievalFailed, reason: $temporal['failure']->reason, usage: $usage);
        }
        $needs = $temporal['needs'];
        foreach ($needs as &$need) {
            if (($topicalCurrent[$need['need_id']]->reason ?? null) === 'topical_current_reuse_capacity') {
                foreach ($need['temporal_targets'] ?? [] as $index => $target) {
                    if ($target['mode'] === 'current') {
                        $need['temporal_targets'][$index]['status'] = 'capacity_limited';
                    }
                }
            }
        }
        unset($need);
        $results = $temporal['results'];
        $needsById = collect($needs)->keyBy('need_id');
        // A corpus cutover during multi-need execution must not mix generations.
        if (! $authorised->workspace->newQuery()->whereKey($authorised->workspace->id)
            ->where('active_workspace_corpus_generation_id', $authorised->activeCorpusGeneration?->id)->exists()) {
            return new RetrievalResult(RetrievalOutcome::RetrievalFailed, reason: 'active_corpus_changed', usage: $usage);
        }
        // Revalidate every need after all provider work, before the global allocation.
        foreach ($results as $id => $result) {
            if (isset($scopeFacts[$id])) {
                $fresh = $this->eligibility->scopeFact($authorised, $plans[$id], $evaluatedAt);
                if (! $fresh instanceof ApplicabilityScopeFact || $fresh->value !== $scopeFacts[$id]->value) {
                    return new RetrievalResult(RetrievalOutcome::RetrievalFailed, reason: 'scope_record_changed', usage: $usage);
                }

                continue;
            }
            $eligible = $this->eligibility->handle($authorised, $plans[$id], $evaluatedAt);
            if ($eligible->outcome === RetrievalOutcome::ClarificationRequired) {
                return new RetrievalResult(RetrievalOutcome::ClarificationRequired,
                    reason: $eligible->reason, clarificationSource: $eligible->clarificationSource, usage: $usage,
                    clarificationQuestion: $eligible->clarificationQuestion);
            }
            $targetVersions = isset($needsById[$id]['temporal_targets'])
                ? collect($needsById[$id]['temporal_targets'])->flatMap(fn ($target) => array_column($target['versions'], 'version_id'))->unique()->values()->all()
                : null;
            $candidates = $this->hydrateAndRecheck($result->candidates,
                $targetVersions !== null ? ['primary' => $targetVersions]
                    : ($eligible->canSearch() ? $eligible->documentPublicIdsBySide : []), $authorised);
            $byKey = collect($result->candidates)->keyBy(fn ($c) => $c['side'].':'.$c['chunk_id']);
            $candidates = array_map(fn ($c) => [...($byKey[$c['side'].':'.$c['chunk_id']]), ...$c], $candidates);
            $results[$id] = new RetrievalResult($targetVersions !== null && $candidates !== [] ? RetrievalOutcome::EvidenceFound : ($eligible->canSearch() ? $result->outcome : $eligible->outcome),
                $candidates, reason: $eligible->reason ?? $result->reason,
                resolvedTemporalAuthority: $this->resolvedTemporalAuthority($plans[$id], $eligible, $evaluatedAt),
                resolvedApplicabilityLocation: ['resolved_location_public_id' => $eligible->resolvedLocationPublicId],
                lineage: $result->lineage);
        }
        $selection = (new NeedsEvidenceSelection)->selectWithCoverage($needs, $results);
        $selected = $selection['candidates'];
        $coverage = [];
        foreach ($needs as $need) {
            $id = $need['need_id'];
            unset($need['supports_need_id']);
            $result = $results[$id];
            if (isset($need['temporal_targets'])) {
                foreach ($need['temporal_targets'] as &$target) {
                    if ($target['status'] === 'searched' && ! array_any($selected,
                        fn ($candidate) => $candidate['need_id'] === $id
                            && in_array($target['target_id'], $candidate['temporal_target_ids'] ?? [], true))) {
                        $target['status'] = array_any($result->candidates,
                            fn ($candidate) => in_array($target['target_id'], $candidate['temporal_target_ids'] ?? [], true))
                            ? 'capacity_limited' : 'filtered';
                    }
                }
                unset($target);
            }
            $coverage[] = [...$need, 'retrieval_outcome' => $result->outcome->value,
                ...($plan->value['schema_version'] === 'evidence-needs-plan-v4'
                    ? ['request_text_version' => 'extractive-request-v1'] : []),
                'applicability_reference_classifications' => isset($scopeFacts[$id]) ? []
                    : $this->eligibility->referenceClassifications($authorised, $plans[$id]->locationReferences),
                'selection_coverage' => $selection['coverage'][$id],
                ...(isset($scopeFacts[$id]) ? ['scope_fact' => $scopeFacts[$id]->value] : []),
                'retrieval_reason' => $result->reason,
                'selected_chunk_ids' => array_column(array_values(array_filter($selected, fn ($c) => $c['need_id'] === $id)), 'chunk_id'),
                'resolved_temporal_authority' => $result->resolvedTemporalAuthority,
                'resolved_applicability_location' => $result->resolvedApplicabilityLocation];
        }
        $observe?->__invoke('needs_final_evidence', $selected);
        $observe?->__invoke('needs_selection_coverage', $selection['coverage']);

        return new RetrievalResult($selected === [] && $scopeFacts === [] ? RetrievalOutcome::InsufficientEvidence : RetrievalOutcome::EvidenceFound,
            $selected, reason: $selected === [] && $coverage !== []
                && count(array_filter($coverage, fn ($need) => $need['retrieval_reason'] === 'no_previous_version')) === count($coverage)
                    ? 'no_previous_version' : null,
            lineage: ['planner' => $plan->lineage->toArray(), 'evidence_selection' => 'bounded-needs-k5-v3',
                'temporal_alternatives' => ['version' => 'bounded-temporal-targets-v1',
                    'additional_search_lanes' => $temporal['additional_search_lanes']],
                'topical_discovery_work' => $discoveryWork,
                'selection_coverage' => $selection['coverage'],
                'needs' => array_map(fn ($r) => $r->lineage, $results)], usage: $usage, evidenceNeeds: $coverage);
    }

    /**
     * Current-side discovery precedes historical eligibility. No historical pair
     * can enter search until one authorised current chunk anchors the family.
     *
     * @return array{decision: string, family_id: ?string, chunk_id: ?string, usage: list<array<string, mixed>>}
     */
    private function selectTopicalComparisonFamily(
        AuthorisedKnowledgeScope $authorised,
        RetrievalPlan $plan,
        int $candidateK,
        CarbonImmutable $evaluatedAt,
        array &$diagnostics = [],
        bool $optionalDiscovery = false,
    ): array {
        $currentPlan = new RetrievalPlan($plan->query, RetrievalTemporalMode::Current,
            null, null, $plan->locationReferences, null, $plan->classifierLineage,
            $plan->classifierUsage, evidenceNeed: $plan->evidenceNeed);
        $eligible = $this->eligibility->handle($authorised, $currentPlan, $evaluatedAt);
        $diagnostics = ['stop_stage' => 'discovery_eligibility',
            'search_requests' => 0, 'reranker_requests' => 0, 'reviewer_requests' => 0,
            'charged_to_logical_lane_limit' => false,
            'eligibility_outcome' => $eligible->outcome->value, 'eligibility_reason' => $eligible->reason,
            'active_corpus_present' => $authorised->activeCorpusGeneration !== null,
            'search_candidate_count' => null, 'hydrated_candidate_count' => null,
            'reranked_candidate_count' => null, 'reviewer_candidate_count' => null,
            'reviewer_invoked' => false];
        if (! $eligible->canSearch() || $authorised->activeCorpusGeneration === null) {
            return ['decision' => 'no_match', 'family_id' => null, 'chunk_id' => null, 'usage' => [], 'diagnostics' => $diagnostics];
        }
        $corpus = $authorised->activeCorpusGeneration;
        $policy = $corpus->sparse_space_generation_id === null ? null : $this->policies->handle($corpus);
        $diagnostics['search_requests'] = 1;
        $search = $this->client->search($authorised->workspace, $corpus, $eligible,
            $plan->query, $candidateK, $policy);
        $usage = $search->usage;
        $diagnostics['completed_usage'] = $usage;
        $diagnostics['search_candidate_count'] = count($search->candidates);
        $fresh = $this->eligibility->handle($authorised, $currentPlan, $evaluatedAt);
        $hydrated = $this->hydrateAndRecheck($search->candidates,
            $fresh->canSearch() ? $fresh->documentPublicIdsBySide : [], $authorised);
        $diagnostics['hydrated_candidate_count'] = count($hydrated);
        $diagnostics['stop_stage'] = $search->candidates === [] ? 'discovery_search' : 'discovery_hydration';
        if ($hydrated === []) {
            return ['decision' => 'no_match', 'family_id' => null, 'chunk_id' => null, 'usage' => $usage, 'diagnostics' => $diagnostics];
        }
        $ranked = [];
        if ($policy !== null) {
            $this->policies->assertSearchLineage($policy, $search->lineage);
            if (($search->lineage['sparse_space_generation_id'] ?? null)
                !== $corpus->sparseSpaceGeneration?->public_id) {
                throw new RetrievalException('Sparse generation lineage does not match the active corpus.');
            }
            $diagnostics['reranker_requests'] = 1;
            $reranked = $this->client->rerank($authorised->workspace, $plan->query, $hydrated, $policy);
            $ranked = $reranked['candidates'];
            $diagnostics['reranked_candidate_count'] = count($reranked['candidates']);
            $this->policies->assertRerankerLineage($policy, $reranked['profile']);
            $usage = [...$usage, ...$reranked['usage']];
            $diagnostics['completed_usage'] = $usage;
            $byChunk = collect($hydrated)->keyBy('chunk_id');
            $hydrated = collect($reranked['candidates'])->map(
                fn (array $item) => $byChunk->get($item['chunk_id'])
            )->filter()->values()->all();
        }
        $fresh = $this->eligibility->handle($authorised, $currentPlan, $evaluatedAt);
        $candidates = $this->hydrateAndRecheck(array_slice($hydrated, 0, 12),
            $fresh->canSearch() ? $fresh->documentPublicIdsBySide : [], $authorised);
        $diagnostics['reviewer_candidate_count'] = count($candidates);
        $diagnostics['stop_stage'] = 'discovery_recheck';
        if ($candidates === []) {
            return ['decision' => 'no_match', 'family_id' => null, 'chunk_id' => null, 'usage' => $usage, 'diagnostics' => $diagnostics];
        }
        $diagnostics['reviewer_invoked'] = true;
        $diagnostics['reviewer_requests'] = 1;
        $selection = $this->client->selectTopicalCompareFamily($authorised->workspace,
            $plan->query, $candidates, $optionalDiscovery);
        $diagnostics['stop_stage'] = 'reviewer_result';

        return [...$selection, 'usage' => [...$usage, $selection['usage']], 'diagnostics' => $diagnostics,
            'current_candidates' => $hydrated, 'current_document_ids' => $fresh->documentPublicIdsBySide['primary'] ?? [],
            'ranked' => $ranked, 'policy' => $policy];
    }

    /** Only attributable optional transport/provider outages qualify, never unknown/integrity failures. */
    private function optionalDiscoveryUnavailable(RetrievalException $exception): bool
    {
        if (! $exception instanceof RetrievalExecutionException) {
            return false;
        }
        if ($exception->serviceHttpStatus !== null && $exception->serviceHttpStatus >= 400 && $exception->serviceHttpStatus < 500) {
            return false;
        }
        $failure = $exception->observation;
        if (! in_array($failure->execution, ['provider_api', 'infrastructure', 'orchestration'], true)
            || ! in_array($failure->stage, ['transport_orchestration', 'dense_embedding', 'sparse_encoding', 'qdrant_dense_search', 'qdrant_sparse_search', 'reranker'], true)
            || ($failure->httpStatus !== null && ! in_array($failure->httpStatus, [502, 503, 504], true))) {
            return false;
        }

        return in_array($failure->category, ['timeout', 'connection_error'], true)
            || ($failure->category === 'provider_http_error' && in_array($failure->httpStatus, [502, 503, 504], true));
    }

    /** Reuse only the same eligible current version(s); never issue a replacement search. */
    private function reuseTopicalCurrent(AuthorisedKnowledgeScope $scope, RetrievalPlan $bound, array $discovery, CarbonImmutable $at): RetrievalResult
    {
        $current = new RetrievalPlan($bound->query, RetrievalTemporalMode::Current, null, null,
            $bound->locationReferences, null, $bound->classifierLineage, $bound->classifierUsage, evidenceNeed: $bound->evidenceNeed);
        $eligible = $this->eligibility->handle($scope, $current, $at);
        $ids = $eligible->documentPublicIdsBySide['primary'] ?? [];
        if (! $eligible->canSearch() || $ids === [] || array_diff($ids, $discovery['current_document_ids']) !== []) {
            return new RetrievalResult(RetrievalOutcome::InsufficientEvidence, reason: 'topical_current_reuse_capacity',
                lineage: ['selection_stop_stage' => 'capacity_limited']);
        }
        $candidates = $this->hydrateAndRecheck($discovery['current_candidates'], ['primary' => $ids], $scope);
        $policy = $discovery['policy'];
        if ($policy !== null) {
            $ranked = collect($discovery['ranked'])->keyBy(fn ($row) => $row['side'].':'.$row['chunk_id']);
            $candidates = collect($candidates)->map(fn ($candidate) => $this->thresholdCandidate($candidate, $ranked, $policy))
                ->filter()->sortBy('rank')->take($policy->final_evidence_k)->values()->all();
        }

        return new RetrievalResult($candidates === [] ? RetrievalOutcome::InsufficientEvidence : RetrievalOutcome::EvidenceFound,
            $candidates, reason: $candidates === [] ? 'topical_current_evidence_unavailable' : null,
            resolvedTemporalAuthority: $this->resolvedTemporalAuthority($current, $eligible, $at),
            resolvedApplicabilityLocation: ['resolved_location_public_id' => $eligible->resolvedLocationPublicId],
            lineage: ['selection_stop_stage' => $candidates === [] ? 'threshold_filtered' : null, 'topical_current_reused' => true]);
    }

    private function thresholdCandidate(array $candidate, Collection $ranked, EvidenceThresholdPolicy $policy): ?array
    {
        $row = $ranked->get($candidate['side'].':'.$candidate['chunk_id']);
        if (! is_array($row) || (float) $row['score'] < $policy->evidence_threshold) {
            return null;
        }

        return [...$candidate, 'fused_score' => $candidate['score'], 'fused_rank' => $candidate['rank'],
            'score' => (float) $row['score'], 'rank' => $row['rank'],
            'evidence_threshold_policy_version' => $policy->version, 'evidence_threshold_policy_fingerprint' => $policy->fingerprint,
            'reranker_provider' => $policy->reranker_provider, 'reranker_model' => $policy->reranker_model,
            'reranker_adapter_version' => $policy->reranker_adapter_version];
    }

    private function comparisonPlanForFamily(RetrievalPlan $plan, string $familyId): RetrievalPlan
    {
        $need = $plan->evidenceNeed;
        $binding = $need['subject_binding'];
        $need['subject_binding'] = [...$binding, 'subject_status' => 'matched',
            'family_ids' => [$familyId]];

        return new RetrievalPlan($plan->query, $plan->temporalMode, $plan->explicitDate,
            $plan->temporalReference, $plan->locationReferences, $plan->clarificationReason,
            $plan->classifierLineage, $plan->classifierUsage, $plan->versionTransitionBoundary,
            $plan->factDate, $plan->requestedEvidenceType, $need);
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
        bool $recordPlannerUsage = true,
    ): RetrievalResult {
        $currentStage = 'transport_orchestration';
        $usage = [];
        try {
            $plan = $planOverride ?? $this->client->plan($authorised->workspace, $question, $evaluatedAt);
            if ($recordPlannerUsage) {
                $usage[] = $plan->classifierUsage->toArray();
            }
            $observe?->__invoke('plan', [
                'query' => $plan->query,
                'temporal_mode' => $plan->temporalMode->value,
                'explicit_date' => $plan->explicitDate?->toDateString(),
                'temporal_reference' => $plan->temporalReference === null ? null : [
                    'kind' => $plan->temporalReference['kind']->value,
                    'value' => $plan->temporalReference['value'],
                ],
                'version_transition_boundary' => $plan->versionTransitionBoundary === null ? null : [
                    'kind' => $plan->versionTransitionBoundary['kind']->value,
                    'value' => $plan->versionTransitionBoundary['value'],
                ],
                'fact_date' => $plan->factDate,
                'requested_evidence_type' => $plan->requestedEvidenceType->value,
                'location_references' => $plan->locationReferences,
                'clarification_reason' => $plan->clarificationReason?->value,
                'classifier_lineage' => $plan->classifierLineage->toArray(),
                'usage' => $recordPlannerUsage ? [$plan->classifierUsage->toArray()] : [],
            ]);
            $eligible = $this->eligibility->handle($authorised, $plan, $evaluatedAt);
            $observe?->__invoke('eligibility', [
                'outcome' => $eligible->outcome->value,
                'document_public_ids_by_side' => $eligible->documentPublicIdsBySide,
                'reason' => $eligible->reason,
                'resolved_location_public_id' => $eligible->resolvedLocationPublicId,
                'clarification_question' => $eligible->clarificationQuestion,
                'reference_classifications' => $this->eligibility->referenceClassifications($authorised, $plan->locationReferences),
            ]);
            if (! $eligible->canSearch()) {
                return new RetrievalResult(
                    $eligible->outcome,
                    reason: $eligible->reason,
                    clarificationSource: $eligible->clarificationSource,
                    clarificationQuestion: $eligible->clarificationQuestion,
                    resolvedTemporalAuthority: $this->resolvedTemporalAuthority($plan, $eligible, $evaluatedAt),
                    resolvedApplicabilityLocation: ['resolved_location_public_id' => $eligible->resolvedLocationPublicId],
                    lineage: ['planner' => $plan->classifierLineage->toArray()],
                    usage: $usage,
                );
            }
            $corpus = $authorised->activeCorpusGeneration;
            if ($corpus === null) {
                return new RetrievalResult(
                    RetrievalOutcome::NoEligibleEvidence,
                    resolvedTemporalAuthority: $this->resolvedTemporalAuthority($plan, $eligible, $evaluatedAt),
                    resolvedApplicabilityLocation: ['resolved_location_public_id' => $eligible->resolvedLocationPublicId],
                    lineage: ['planner' => $plan->classifierLineage->toArray()],
                    usage: $usage,
                );
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
            $usage = [...$usage, ...$search->usage];
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
                return new RetrievalResult(RetrievalOutcome::NoRetrievalCandidates, lineage: ['selection_stop_stage' => 'search_empty'], usage: $usage);
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
                return new RetrievalResult(RetrievalOutcome::NoRetrievalCandidates, lineage: ['selection_stop_stage' => 'hydration_filtered'], usage: $usage);
            }
            if ($policy === null) {
                return $this->evidenceFoundResult($hydrated, $plan, $freshEligible, $evaluatedAt, $search->lineage, $usage);
            }

            $currentStage = 'reranker';
            $reranked = $this->client->rerank(
                $authorised->workspace,
                $plan->query,
                $hydrated,
                $policy,
            );
            $usage = [...$usage, ...$reranked['usage']];
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
                return new RetrievalResult(RetrievalOutcome::InsufficientEvidence, lineage: ['selection_stop_stage' => 'threshold_filtered'], usage: $usage);
            }

            $sides = array_keys($eligible->documentPublicIdsBySide);
            if (count($sides) === 2) {
                $qualified = $this->validatedComparisonCandidates($qualified);
                $observe?->__invoke('comparison_validated', $qualified->all());
            }
            $qualified = $plan->evidenceNeed === null
                ? $this->qualifiedForRequestedEvidence($qualified, $plan->requestedEvidenceType) : $qualified;
            $observe?->__invoke('claim_type_qualified', $qualified->all());
            if ($qualified->isEmpty()) {
                return new RetrievalResult(
                    RetrievalOutcome::InsufficientEvidence,
                    resolvedTemporalAuthority: $this->resolvedTemporalAuthority($plan, $finalEligible, $evaluatedAt),
                    usage: $usage,
                );
            }
            if (count($sides) === 2 && collect($sides)->contains(
                fn (string $side): bool => ! $qualified->contains(
                    fn (array $candidate): bool => $candidate['side'] === $side
                )
            )) {
                return new RetrievalResult(RetrievalOutcome::ComparisonScopeIncomplete, usage: $usage);
            }
            $accepted = $qualified->groupBy('side')->flatMap(
                fn ($sideCandidates) => $sideCandidates->take($policy->final_evidence_k)
            )->sortBy('rank')->values()->all();
            $observe?->__invoke('final_evidence', $accepted);

            return $this->evidenceFoundResult($accepted, $plan, $finalEligible, $evaluatedAt, [
                ...$search->lineage,
                'reranker_profile' => $reranked['profile'],
                'evidence_threshold_policy_version' => $policy->version,
                'evidence_threshold_policy_fingerprint' => $policy->fingerprint,
            ], $usage);
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

            return new RetrievalResult(
                RetrievalOutcome::RetrievalFailed,
                lineage: [
                    'planner' => isset($plan) ? $plan->classifierLineage->toArray() : null,
                    'failure' => $failure,
                ],
                usage: [...$usage, ...(is_array($failure['usage'] ?? null) ? $failure['usage'] : [])],
            );
        }
    }

    /** @param list<array<string, mixed>> $candidates @param array<string, mixed> $lineage */
    private function evidenceFoundResult(
        array $candidates,
        RetrievalPlan $plan,
        EligibleRetrievalScope $eligible,
        CarbonImmutable $evaluatedAt,
        array $lineage,
        array $usage,
    ): RetrievalResult {
        return new RetrievalResult(
            RetrievalOutcome::EvidenceFound,
            $candidates,
            resolvedTemporalAuthority: $this->resolvedTemporalAuthority($plan, $eligible, $evaluatedAt),
            resolvedApplicabilityLocation: [
                'resolved_location_public_id' => $eligible->resolvedLocationPublicId,
            ],
            lineage: [
                'planner' => $plan->classifierLineage->toArray(),
                'retrieval' => $lineage,
            ],
            usage: $usage,
        );
    }

    /**
     * Enforce the current-versus-historical family pairing again after reranking.
     * Candidates from an unpaired family or a document repeated on both sides
     * cannot become final comparison evidence.
     *
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return Collection<int, array<string, mixed>>
     */
    private function validatedComparisonCandidates(Collection $candidates): Collection
    {
        $primary = $candidates->where('side', 'primary');
        $comparison = $candidates->where('side', 'comparison');
        if ($primary->pluck('document_id')->intersect($comparison->pluck('document_id'))->isNotEmpty()) {
            return collect();
        }

        $pairedFamilies = $primary->pluck('document_family_id')->unique()
            ->intersect($comparison->pluck('document_family_id')->unique());

        return $candidates->filter(
            fn (array $candidate): bool => $pairedFamilies->contains($candidate['document_family_id'] ?? null),
        )->values();
    }

    /** @param Collection<int, array<string, mixed>> $candidates @return Collection<int, array<string, mixed>> */
    private function qualifiedForRequestedEvidence(Collection $candidates, RequestedEvidenceType $type): Collection
    {
        if ($type !== RequestedEvidenceType::PersonalRecordStatus) {
            return $candidates;
        }

        return $candidates->filter(function (array $candidate): bool {
            $identity = mb_strtolower(trim(
                ((string) ($candidate['document_family_title'] ?? '')).' '.
                ((string) ($candidate['document_title'] ?? ''))
            ));
            if (preg_match('/\b(policy|procedure|guidance|handbook|standard|protocol)\b/u', $identity) === 1) {
                return false;
            }

            return preg_match('/\b(record|records|register|log|completion|attendance|certificate|transcript|status)\b/u', $identity) === 1;
        })->values();
    }

    /** @return array<string, mixed> */
    private function resolvedTemporalAuthority(RetrievalPlan $plan, EligibleRetrievalScope $eligible, CarbonImmutable $evaluatedAt): array
    {
        return [
            'temporal_mode' => $plan->temporalMode->value,
            'evaluated_at' => $evaluatedAt->toIso8601String(),
            'explicit_date' => $plan->explicitDate?->toDateString(),
            'temporal_reference' => $plan->temporalReference === null ? null : [
                'kind' => $plan->temporalReference['kind']->value,
                'value' => $plan->temporalReference['value'],
            ],
            'version_transition_boundary' => $plan->versionTransitionBoundary === null ? null : [
                'kind' => $plan->versionTransitionBoundary['kind']->value,
                'value' => $plan->versionTransitionBoundary['value'],
            ],
            'fact_date' => $plan->factDate,
            'requested_evidence_type' => $plan->requestedEvidenceType->value,
            'eligible_document_public_ids_by_side' => $eligible->documentPublicIdsBySide,
            ...($plan->evidenceNeed === null ? [] : ['need_id' => $plan->evidenceNeed['need_id'], 'authority' => $plan->evidenceNeed['authority']]),
        ];
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
            'document_title' => mb_substr($document->source_filename, 0, 500),
            'document_family_title' => mb_substr($document->family->name, 0, 500),
            'section_heading' => $this->sectionHeading($chunk),
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

    private function sectionHeading(DocumentChunk $chunk): ?string
    {
        $generationId = $chunk->document->active_extraction_projection_generation_id;
        if ($generationId === null || ! is_array($chunk->provenance)) {
            return null;
        }
        $elementIds = collect($chunk->provenance)
            ->pluck('normalised_element_id')
            ->filter(fn (mixed $value): bool => is_string($value))
            ->unique()
            ->take(32)
            ->values();
        if ($elementIds->isEmpty()) {
            return null;
        }
        $heading = DocumentExtractionProjectionElement::query()
            ->where('workspace_id', $chunk->workspace_id)
            ->where('document_id', $chunk->document_id)
            ->where('projection_generation_id', $generationId)
            ->where('kind', 'heading')
            ->whereIn('element_id', $elementIds)
            ->orderBy('ordinal')
            ->value('text');

        return is_string($heading) && trim($heading) !== ''
            ? mb_substr(trim($heading), 0, 500)
            : null;
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
