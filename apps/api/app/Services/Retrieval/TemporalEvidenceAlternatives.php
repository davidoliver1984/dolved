<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\RetrievalOutcome;
use App\Enums\RetrievalTemporalMode;
use App\Models\Document;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\ExplicitTemporalReferences;
use App\Support\Retrieval\NeedsEvidenceSelection;
use App\Support\Retrieval\RetrievalPlan;
use App\Support\Retrieval\RetrievalResult;
use Carbon\CarbonImmutable;

/** C1: existing matched families only. Never discovers a family or changes authority. */
final readonly class TemporalEvidenceAlternatives
{
    public function __construct(private EligibilityResolver $eligibility, private DocumentAuthorityTimeline $timeline) {}

    public function augment(AuthorisedKnowledgeScope $scope, array $needs, array $plans, array $results,
        CarbonImmutable $evaluatedAt, callable $execute, ?callable $observe = null): array
    {
        $lanes = NeedsEvidenceSelection::searchLanes($needs);
        $usage = [];
        $rechecks = [];
        foreach ($needs as &$need) {
            $id = $need['need_id'];
            $plan = $plans[$id];
            $binding = $plan->evidenceNeed['subject_binding'] ?? [];
            $references = ExplicitTemporalReferences::forNeed($need);
            if ($need['operation'] !== 'retrieve' || $references === []
                || ($binding['subject_status'] ?? null) !== 'matched' || count($binding['family_ids'] ?? []) !== 1) {
                continue;
            }
            $familyId = $binding['family_ids'][0];
            $base = $results[$id];
            $eligible = $this->eligibility->handle($scope, $plan, $evaluatedAt);
            $baseVersions = $eligible->documentPublicIdsBySide['primary'] ?? [];
            $rechecks[] = [$plan, $baseVersions];
            $targets = [];
            $candidates = $base->candidates;
            $baseAt = $plan->temporalMode === RetrievalTemporalMode::Current ? $evaluatedAt : $plan->explicitDate;
            $targets[] = $this->target($scope, $id.':requested', $id, $familyId, 'requested',
                $plan->temporalMode === RetrievalTemporalMode::Current ? 'current' : 'provisional',
                $baseAt, null, $baseVersions, $this->selectionStatus($base));
            $targets[0]['reason'] = $base->reason ?? ($base->lineage['selection_stop_stage'] ?? null);
            foreach ($candidates as &$candidate) {
                $candidate['temporal_target_ids'] = [$id.':requested'];
            }
            unset($candidate);
            $admitted = 0;
            if ($plan->temporalMode !== RetrievalTemporalMode::Current) {
                $references[] = null; // Current is available as a distinct target, never labelled requested history.
            }
            foreach ($references as $index => $reference) {
                $at = $reference === null ? $evaluatedAt : CarbonImmutable::parse($reference['at']);
                $dated = new RetrievalPlan($plan->query, $reference === null ? RetrievalTemporalMode::Current : RetrievalTemporalMode::ValidAtDate, $reference === null ? null : $at, null,
                    $plan->locationReferences, null, $plan->classifierLineage, $plan->classifierUsage,
                    factDate: $plan->factDate, requestedEvidenceType: $plan->requestedEvidenceType, evidenceNeed: $plan->evidenceNeed);
                $datedEligible = $this->eligibility->handle($scope, $dated, $evaluatedAt);
                $versions = $datedEligible->documentPublicIdsBySide['primary'] ?? [];
                $rechecks[] = [$dated, $versions];
                $targetId = $reference === null ? $id.':current' : $id.':date:'.($index + 1);
                $target = $this->target($scope, $targetId, $id, $familyId, 'precautionary', $reference === null ? 'current' : 'dated', $at,
                    $reference, $versions, 'unsearched');
                $alreadySearched = $versions !== [] && array_diff($versions, array_column($candidates, 'document_id')) === [];
                if ($alreadySearched || ($versions !== [] && $versions === $baseVersions)) {
                    foreach ($candidates as &$candidate) {
                        if (in_array($candidate['document_id'], $versions, true)) {
                            $candidate['temporal_target_ids'][] = $targetId;
                        }
                    }
                    unset($candidate);
                    $target['status'] = $candidates === [] ? $this->selectionStatus($base) : 'searched';
                } elseif (! $datedEligible->canSearch() || $versions === []) {
                    $target['status'] = 'ineligible';
                    $target['reason'] = $datedEligible->reason;
                } elseif ($lanes >= NeedsEvidenceSelection::MAX_SEARCH_LANES || $admitted >= 1) {
                    $target['status'] = 'capacity_limited';
                } else {
                    $lanes++;
                    $admitted++;
                    $alternative = $execute($dated, $id, $targetId);
                    $usage = [...$usage, ...$alternative->usage];
                    if ($alternative->outcome === RetrievalOutcome::RetrievalFailed) {
                        return ['failure' => $alternative, 'usage' => $usage];
                    }
                    // execute() performs the same final eligibility/corpus checks as requested lanes.
                    $target['status'] = $this->selectionStatus($alternative);
                    $target['reason'] = $alternative->reason ?? ($alternative->lineage['selection_stop_stage'] ?? null);
                    foreach ($alternative->candidates as $candidate) {
                        $candidates[] = [...$candidate, 'temporal_target_ids' => [$targetId]];
                    }
                }
                $targets[] = $target;
            }
            $need['temporal_targets'] = $targets;
            $observe?->__invoke('need.'.$id.'.temporal_targets', $targets);
            $results[$id] = new RetrievalResult($candidates === [] ? $base->outcome : RetrievalOutcome::EvidenceFound,
                $candidates, reason: $base->reason, lineage: $base->lineage,
                resolvedTemporalAuthority: $base->resolvedTemporalAuthority,
                resolvedApplicabilityLocation: $base->resolvedApplicabilityLocation);
        }
        unset($need);
        // Provider work can span a corpus/governance change. Recheck all targets,
        // including the requested lanes, after the last alternative finishes.
        if (! $scope->workspace->newQuery()->whereKey($scope->workspace->id)
            ->where('active_workspace_corpus_generation_id', $scope->activeCorpusGeneration?->id)->exists()) {
            return ['failure' => new RetrievalResult(RetrievalOutcome::RetrievalFailed, reason: 'active_corpus_changed'), 'usage' => $usage];
        }
        foreach ($rechecks as [$checkPlan, $versions]) {
            $fresh = $this->eligibility->handle($scope, $checkPlan, $evaluatedAt);
            if (($fresh->documentPublicIdsBySide['primary'] ?? []) !== $versions) {
                return ['failure' => new RetrievalResult(RetrievalOutcome::RetrievalFailed, reason: 'temporal_authority_changed'), 'usage' => $usage];
            }
        }

        return ['needs' => $needs, 'results' => $results, 'usage' => $usage,
            'additional_search_lanes' => $lanes - NeedsEvidenceSelection::searchLanes($needs)];
    }

    private function target(AuthorisedKnowledgeScope $scope, string $id, string $needId, string $familyId,
        string $purpose, string $mode, ?CarbonImmutable $at, ?array $reference, array $versions, string $status): array
    {
        $intervals = [];
        foreach ($scope->workspace->documents()->whereIn('public_id', $versions)->with('family')->get() as $document) {
            if ($document->family->public_id !== $familyId) {
                throw new \LogicException('Temporal target escaped its bound family.');
            }
            $attained = $this->timeline->attainedVersions($document->family);
            $index = $attained->search(fn (Document $item) => $item->id === $document->id);
            $next = $index !== false && isset($attained[$index + 1]) ? $this->timeline->authorityStart($attained[$index + 1]) : null;
            $end = $document->withdrawn_at;
            if ($next !== null && ($end === null || $next->lt($end))) {
                $end = $next;
            }
            $intervals[] = ['version_id' => $document->public_id,
                'start' => $this->timeline->authorityStart($document)?->toIso8601String(), 'end' => $end?->toIso8601String()];
        }

        return ['target_id' => $id, 'need_id' => $needId, 'family_id' => $familyId, 'purpose' => $purpose,
            'mode' => $mode, 'at' => $at?->toIso8601String(), 'reference' => $reference,
            'versions' => $intervals, 'status' => $status, 'reason' => null];
    }

    private function selectionStatus(RetrievalResult $result): string
    {
        if ($result->candidates !== []) {
            return 'searched';
        }

        return in_array($result->lineage['selection_stop_stage'] ?? null, ['hydration_filtered', 'threshold_filtered'], true)
            ? 'filtered' : 'empty';
    }
}
