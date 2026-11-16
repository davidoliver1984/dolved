<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Actions\Retrieval\RetrieveWorkspaceEvidence;
use App\Enums\GenerationSide;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationException;
use App\Models\DocumentChunk;
use App\Services\Retrieval\EligibilityResolver;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Generation\GenerationEvidence;
use App\Support\Retrieval\ApplicabilityScopeFact;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\CurrentPolicyScope;
use App\Support\Retrieval\EvidenceNeedsPlan;
use Carbon\CarbonImmutable;

/** Deterministic question pool. Associations describe scope, never answer ownership. */
final readonly class CurrentPolicyPool
{
    public function __construct(private RetrieveWorkspaceEvidence $retrieval, private DocumentAuthorityTimeline $timeline) {}

    public function assemble(AuthorisedKnowledgeScope $scope, EvidenceNeedsPlan $plan, array $results, CarbonImmutable $at): array
    {
        $sources = $evidence = $facts = $requests = [];
        $dispositions = CurrentPolicyScope::dispositions($plan->value);
        $needs = $plan->executionNeeds();
        if (count($needs) > 3 || array_diff(array_keys($results), array_column($needs, 'need_id')) !== []) {
            throw new GenerationException('Unexpected current-policy lane.');
        }
        foreach ($needs as $need) {
            $id = $need['need_id'];
            $result = $results[$id] ?? throw new GenerationException('Missing current-policy lane result.');
            if ($result instanceof ApplicabilityScopeFact) {
                $facts[] = $result->value;

                continue;
            }
            if ($result->outcome !== RetrievalOutcome::EvidenceFound) {
                // Unknown/security failures must not be disguised as safe partial answers.
                $failure = $result->lineage['failure'] ?? [];
                $safeFailure = in_array($failure['category'] ?? null, ['timeout', 'rate_limited', 'connection_error', 'invalid_provider_response'], true)
                    && in_array($failure['stage'] ?? null, ['dense_embedding', 'sparse_encoding', 'reranker'], true)
                    && ($failure['execution'] ?? null) === 'provider_api';
                if ($result->outcome === RetrievalOutcome::RetrievalFailed && ! $safeFailure) {
                    throw new GenerationException('Current-policy retrieval failed; no answer is persisted.');
                }
                $dispositions[] = ['version' => 'current-policy-disposition-v2', 'need_id' => $id,
                    'status' => $result->outcome === RetrievalOutcome::ClarificationRequired ? 'ambiguity' : ($safeFailure ? 'failed' : 'unavailable'),
                    'reason' => $failure['category'] ?? $result->reason ?? $result->outcome->value,
                    'affected_spans' => $need['requested_information'] === 'mixed' ? $need['current_spans'] : [$need['question_span']],
                    'message' => $safeFailure ? 'Retrieval failed for this part; this does not establish that the sources lack the information.' : ($result->clarificationQuestion ?? 'The selected authorised evidence does not establish this part.')];

                continue;
            }
            if (count($result->candidates) > 5 || $result->candidates === []) {
                throw new GenerationException('Invalid first-five retrieval result.');
            }
            $this->retrieval->recheckCurrentPolicy($scope, $plan->retrievalPlan($id), $result->candidates, $at);
            if ($need['applicability']['kind'] === 'named' && ($result->resolvedApplicabilityLocation['resolved_location_public_id'] ?? null) === null) {
                $classifications = app(EligibilityResolver::class)->referenceClassifications($scope, [$need['applicability']['source']['quote']]);
                if ($classifications === [] || ! array_all($classifications, fn ($c) => in_array($c['kind'], ['organisation', 'document'], true))) {
                    $dispositions[] = ['version' => 'current-policy-disposition-v2', 'need_id' => $id,
                        'status' => 'coverage_unestablished', 'reason' => 'unresolved_named_location',
                        'affected_spans' => [$need['applicability']['source']],
                        'message' => 'The named location could not be matched to a registered site. General guidance does not establish coverage of that location.'];
                }
            }
            $requests[] = $need['aspect'];
            foreach ($result->candidates as $candidate) {
                $chunk = DocumentChunk::where('workspace_id', $scope->workspace->id)->where('public_id', $candidate['chunk_id'])->with('document.family', 'document.applicabilitySnapshot.locations')->firstOrFail();
                $document = $chunk->document;
                $snapshot = $document->applicabilitySnapshot;
                $versions = $this->timeline->attainedVersions($document->family);
                $position = $versions->search(fn ($v) => $v->id === $document->id);
                $next = is_int($position) && isset($versions[$position + 1]) ? $this->timeline->authorityStart($versions[$position + 1]) : null;
                $end = $document->withdrawn_at === null ? $next : ($next === null || $document->withdrawn_at->lt($next) ? $document->withdrawn_at : $next);
                $association = ['need_id' => $id, 'authority' => 'current', 'evaluated_at' => $at->toIso8601String(),
                    'location_id' => $result->resolvedApplicabilityLocation['resolved_location_public_id'] ?? null];
                $source = ['evidence_id' => '', 'chunk_id' => $chunk->public_id, 'document_id' => $document->public_id,
                    'family_id' => $document->family->public_id, 'title' => $document->family->name,
                    'text' => $chunk->text, 'effective_from' => $document->effective_from?->toIso8601String(),
                    'authority_start' => $this->timeline->authorityStart($document)?->toIso8601String(),
                    'authority_end_exclusive' => $end?->toIso8601String(),
                    'status' => $document->governance_status->value,
                    'applicability' => ['scope' => $snapshot?->scope->value,
                        'locations' => $snapshot?->locations->map(fn ($l) => ['id' => $l->public_id, 'name' => $l->name])->values()->all() ?? []]];
                $key = $chunk->public_id;
                if (isset($sources[$key])) {
                    $prior = $sources[$key];
                    unset($prior['associations']);
                    $source['evidence_id'] = $prior['evidence_id'];
                    if ($prior !== $source) {
                        throw new GenerationException('Conflicting physical source identity.');
                    }
                    $sources[$key]['associations'][] = $association;

                    continue;
                }
                $handle = sprintf('ev-%02d', count($sources) + 1);
                $source['evidence_id'] = $handle;
                $sources[$key] = [...$source, 'associations' => [$association]];
                $evidence[] = new GenerationEvidence($handle, $chunk->text, $chunk->id, $document->id,
                    $chunk->ingestion_event_claim_id, $chunk->provenance, ['mode' => 'current', 'evaluated_at' => $at->toIso8601String()],
                    $source['applicability'], GenerationSide::Primary);
            }
        }
        $characters = array_sum(array_map(fn ($s) => mb_strlen($s['text']), $sources));

        return ['sources' => array_values($sources), 'evidence' => $evidence, 'facts' => $facts,
            'requests' => $requests, 'dispositions' => $dispositions,
            'capacity' => count($sources) > 15 || $characters > 60000
                ? ['policy' => 'current-policy-source-v1', 'characters' => $characters, 'passages' => count($sources)] : null];
    }

    public static function disclosures(array $dispositions): array
    {
        return array_values(array_map(fn ($d) => implode(' / ', array_column($d['affected_spans'], 'quote')).': '.$d['message'],
            array_filter($dispositions, fn ($d) => $d['status'] !== 'admitted')));
    }
}
