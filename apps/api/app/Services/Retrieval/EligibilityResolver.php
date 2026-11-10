<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\DocumentApplicabilityScope;
use App\Enums\DocumentStatus;
use App\Enums\EligibilityClarificationReason;
use App\Enums\RetrievalClarificationSource;
use App\Enums\RetrievalOutcome;
use App\Enums\RetrievalTemporalMode;
use App\Enums\RetrievalTemporalReferenceKind;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\OrganisationalLocation;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\ApplicabilityScopeFact;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\ClassifierLineage;
use App\Support\Retrieval\EligibleRetrievalScope;
use App\Support\Retrieval\PlannerUsage;
use App\Support\Retrieval\RetrievalPlan;
use App\Support\Retrieval\TemporalResolution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class EligibilityResolver
{
    public function __construct(
        private DocumentAuthorityTimeline $timeline,
        private TemporalPeriodResolver $periods,
        private HistoricalReferenceResolver $historical,
    ) {}

    /**
     * ADR-0039's metadata-only path. Never return these documents as chunk candidates.
     * The same resolver still owns access, location, catalogue and temporal checks.
     */
    public function scopeFact(AuthorisedKnowledgeScope $authorised, RetrievalPlan $plan, CarbonImmutable $evaluatedAt): ApplicabilityScopeFact|EligibleRetrievalScope
    {
        // Reuse the production membership/concealment boundary; a location is not access.
        app(BuildAuthorisedKnowledgeScope::class)
            ->handle($authorised->user, $authorised->workspace->public_id);
        $need = $plan->evidenceNeed;
        if (($need['operation'] ?? null) !== 'scope_enquiry') {
            throw new \InvalidArgumentException('Scope facts require an explicitly planned scope enquiry.');
        }
        $binding = $need['subject_binding'] ?? [];
        if (($binding['ownership'] ?? null) !== 'authorised_workspace'
            || ($binding['subject_status'] ?? null) !== 'matched'
            || count($binding['family_ids'] ?? []) !== 1) {
            return new EligibleRetrievalScope(RetrievalOutcome::ClarificationRequired,
                reason: 'ambiguous_document_subject', clarificationSource: RetrievalClarificationSource::EligibilityResolver);
        }
        $breadth = $need['scope_question'] === 'breadth';
        $location = $plan->locationReferences === [] && $breadth
            ? null : $this->resolveLocations($authorised, $plan->locationReferences);
        if ($location === null && ! $breadth) {
            // A membership fact needs a resolved site; an unbound reference cannot
            // establish either membership or non-membership from a scope snapshot.
            return new EligibleRetrievalScope(RetrievalOutcome::NoEligibleEvidence,
                reason: 'scope_location_unresolved');
        }
        // Resolve the same family/version through the ordinary authority path, but
        // ask for metadata without operational location narrowing. No chunks are read.
        $metadataPlan = new RetrievalPlan($plan->query, $plan->temporalMode,
            $plan->explicitDate, $plan->temporalReference, [], $plan->clarificationReason,
            $plan->classifierLineage, $plan->classifierUsage, $plan->versionTransitionBoundary,
            $plan->factDate, $plan->requestedEvidenceType, $need);
        $eligible = $this->handle($authorised, $metadataPlan, $evaluatedAt);
        $ids = $eligible->documentPublicIdsBySide['primary'] ?? [];
        if (! $eligible->canSearch() || count($ids) !== 1 || isset($eligible->documentPublicIdsBySide['comparison'])) {
            return $eligible;
        }
        $document = Document::query()->where('workspace_id', $authorised->workspace->id)
            ->where('public_id', $ids[0])->with(['family', 'applicabilitySnapshot.locations'])->firstOrFail();
        $snapshot = $document->applicabilitySnapshot;
        if ($snapshot === null || $snapshot->sealed_at === null) {
            return new EligibleRetrievalScope(RetrievalOutcome::NoEligibleEvidence, reason: 'scope_record_unavailable');
        }
        $path = [];
        for ($node = $location; $node !== null; $node = $node->parent) {
            if ($node->workspace_id !== $authorised->workspace->id) {
                throw new \RuntimeException('Scope hierarchy escaped authorised workspace.');
            }
            $path[] = $node->public_id;
        }
        // Prefer the most specific recorded match, independent of pivot row order.
        $matched = collect($path)->map(fn ($id) => $snapshot->locations->firstWhere('public_id', $id))->first(fn ($entry) => $entry !== null);
        $basis = $snapshot->scope === DocumentApplicabilityScope::Universal ? 'universal'
            : ($matched === null ? 'outside_recorded_scope'
                : ($matched->id === $location?->id ? 'direct_match' : 'inherited_match'));
        $source = ['snapshot_id' => $snapshot->id, 'document_version_id' => $document->public_id,
            'scope' => $snapshot->scope->value, 'sealed_at' => $snapshot->sealed_at->toIso8601String(),
            'location_ids' => $snapshot->locations->pluck('public_id')->sort()->values()->all()];

        $recordedLocations = [];
        if ($breadth) {
            foreach ($snapshot->locations->sortBy('public_id') as $entry) {
                if ($entry->workspace_id !== $authorised->workspace->id) {
                    throw new \RuntimeException('Scope location escaped authorised workspace.');
                }
                $ancestors = [];
                for ($parent = $entry->parent; $parent !== null; $parent = $parent->parent) {
                    if ($parent->workspace_id !== $authorised->workspace->id) {
                        throw new \RuntimeException('Scope hierarchy escaped authorised workspace.');
                    }
                    $ancestors[] = $parent->public_id;
                }
                $recordedLocations[] = ['location_id' => $entry->public_id, 'name' => $entry->name,
                    'kind' => $entry->kind, 'ancestor_ids' => $ancestors];
            }
        }

        return new ApplicabilityScopeFact([
            'representation' => $breadth ? ApplicabilityScopeFact::BREADTH_VERSION : ApplicabilityScopeFact::VERSION,
            'need_id' => $need['need_id'],
            'workspace_id' => $authorised->workspace->public_id, 'policy_title' => $document->family->name,
            'family_id' => $document->family->public_id, 'document_version_id' => $document->public_id,
            'citation_type' => 'applicability_snapshot',
            'source_route' => '/api/workspaces/'.$authorised->workspace->public_id.'/document-families/'.$document->family->public_id.'/versions',
            'snapshot' => $source, 'snapshot_digest' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'location_id' => $location?->public_id, 'location_name' => $location?->name,
            'hierarchy_path' => $path, 'matched_location_id' => $matched?->public_id,
            ...($breadth ? ['recorded_locations' => $recordedLocations] : []),
            'evaluated_at' => $evaluatedAt->toIso8601String(),
            'temporal_mode' => $plan->temporalMode->value,
            'authority_start' => $this->timeline->authorityStart($document)?->toIso8601String(),
            'result' => $breadth ? 'recorded_scope' : ($basis === 'outside_recorded_scope' ? 'does_not_apply' : 'applies'),
            'basis' => $breadth ? 'recorded_breadth' : $basis,
            'corpus_id' => $authorised->activeCorpusGeneration?->public_id,
            'resolution' => ['query' => $plan->query, 'need' => $need,
                'temporal_mode' => $plan->temporalMode->value, 'explicit_date' => $plan->explicitDate?->toDateString(),
                'temporal_reference' => $plan->temporalReference === null ? null : [
                    'kind' => $plan->temporalReference['kind']->value, 'value' => $plan->temporalReference['value']],
                'location_references' => $plan->locationReferences,
                'lineage' => $plan->classifierLineage->toArray(), 'usage' => $plan->classifierUsage->toArray()],
        ]);
    }

    public function recheckScopeFact(AuthorisedKnowledgeScope $authorised, array $fact): void
    {
        $workspace = $authorised->workspace->fresh();
        if ($workspace->activeCorpusGeneration?->public_id !== ($fact['corpus_id'] ?? null)) {
            throw new \RuntimeException('Scope fact corpus changed before publication.');
        }
        $r = $fact['resolution'];
        $usage = $r['usage'];
        $usage['execution'] = strtolower($usage['execution']);
        $usage['cost_basis'] = strtolower($usage['cost_basis']);
        $reference = $r['temporal_reference'];
        if ($reference !== null) {
            $reference['kind'] = RetrievalTemporalReferenceKind::from($reference['kind']);
        }
        $plan = new RetrievalPlan($r['query'], RetrievalTemporalMode::from($r['temporal_mode']),
            $r['explicit_date'] === null ? null : CarbonImmutable::parse($r['explicit_date']),
            $reference, $r['location_references'], null,
            ClassifierLineage::fromArray($r['lineage']),
            PlannerUsage::fromArray($usage), evidenceNeed: $r['need']);
        $fresh = $this->scopeFact($authorised, $plan, CarbonImmutable::parse($fact['evaluated_at']));
        if (! $fresh instanceof ApplicabilityScopeFact || $fresh->value !== $fact) {
            throw new \RuntimeException('Scope fact changed before publication.');
        }
    }

    public function handle(
        AuthorisedKnowledgeScope $authorised,
        RetrievalPlan $plan,
        CarbonImmutable $evaluatedAt,
    ): EligibleRetrievalScope {
        if ($plan->temporalMode === RetrievalTemporalMode::ClarificationRequired) {
            return new EligibleRetrievalScope(
                RetrievalOutcome::ClarificationRequired,
                reason: $plan->clarificationReason?->value,
                clarificationSource: RetrievalClarificationSource::Planner,
            );
        }
        if ($authorised->activeCorpusGeneration === null) {
            return new EligibleRetrievalScope(RetrievalOutcome::NoEligibleEvidence);
        }

        $binding = $plan->evidenceNeed['subject_binding'] ?? null;
        if ($binding !== null) {
            if ($binding['ownership'] === 'external_tenant' || $binding['subject_status'] === 'unavailable') {
                return new EligibleRetrievalScope(RetrievalOutcome::NoEligibleEvidence, reason: 'requested_subject_unavailable');
            }
            if ($binding['subject_status'] === 'ambiguous') {
                return new EligibleRetrievalScope(RetrievalOutcome::ClarificationRequired,
                    reason: 'ambiguous_document_subject', clarificationSource: RetrievalClarificationSource::EligibilityResolver);
            }
        }

        // An unresolved demonstrative record reference is not a site constraint.
        // It cannot authorise a record lookup, so ask for the record instead.
        if (($plan->evidenceNeed['applicability']['kind'] ?? null) === 'relational'
            && Str::lower(trim((string) ($plan->evidenceNeed['applicability']['source']['quote'] ?? ''))) === 'this record') {
            return new EligibleRetrievalScope(RetrievalOutcome::ClarificationRequired,
                reason: EligibilityClarificationReason::UnresolvedRecordReference->value,
                clarificationSource: RetrievalClarificationSource::EligibilityResolver);
        }

        $location = $this->resolveLocations($authorised, $plan->locationReferences);

        $families = DocumentFamily::query()
            ->where('workspace_id', $authorised->workspace->id)
            ->when($binding !== null && $binding['subject_status'] === 'matched',
                fn ($query) => $query->whereIn('public_id', $binding['family_ids'])->whereNull('tombstoned_at'))
            ->with(['documents.applicabilitySnapshot.locations'])
            ->get();

        // A positive catalogue fact, never a guess about an ambiguous reference.
        if (($binding['subject_status'] ?? null) === 'matched'
            && count($binding['family_ids']) === 1 && $families->count() === 1
            && ($plan->evidenceNeed['authority']['kind'] ?? null) === 'previous'
            && $families->first()->documents->count() === 1
            && $this->timeline->attainedVersions($families->first())->count() === 1
            && $this->eligible($this->current($families, $evaluatedAt), $authorised, $location)->count() === 1) {
            return new EligibleRetrievalScope(RetrievalOutcome::NoEligibleEvidence,
                reason: 'no_previous_version', resolvedLocationPublicId: $location?->public_id);
        }

        if ($plan->temporalMode === RetrievalTemporalMode::Compare) {
            $needAuthority = $plan->evidenceNeed['authority'] ?? null;
            if ($needAuthority !== null && in_array($needAuthority['kind'], ['transition_period', 'transition_boundary'], true)) {
                return $this->transitionNeed($families, $authorised, $location, $needAuthority, $evaluatedAt);
            }
            $transitionAt = $plan->versionTransitionBoundary === null
                ? null
                : $this->periods->boundaryStart($plan->versionTransitionBoundary['value']);
            if ($plan->versionTransitionBoundary !== null && $transitionAt === null) {
                return new EligibleRetrievalScope(
                    RetrievalOutcome::ClarificationRequired,
                    reason: EligibilityClarificationReason::UnresolvableTemporalPeriod->value,
                    resolvedLocationPublicId: $location?->public_id,
                    clarificationSource: RetrievalClarificationSource::EligibilityResolver,
                );
            }
            $primary = $this->current($families, $transitionAt ?? $evaluatedAt);
            $comparison = $transitionAt === null
                ? $this->comparison($families, $plan, $evaluatedAt, $primary)
                : $this->current($families, $transitionAt->subMicrosecond());
            if ($comparison instanceof EligibilityClarificationReason) {
                return new EligibleRetrievalScope(
                    RetrievalOutcome::ClarificationRequired,
                    reason: $comparison->value,
                    resolvedLocationPublicId: $location?->public_id,
                    clarificationSource: RetrievalClarificationSource::EligibilityResolver,
                );
            }
            [$primary, $comparison] = $this->distinctComparisonVersions($primary, $comparison);
            $primary = $this->eligible($primary, $authorised, $location);
            $comparison = $this->eligible($comparison, $authorised, $location);
            if ($primary->isEmpty() || $comparison->isEmpty()) {
                return new EligibleRetrievalScope(
                    RetrievalOutcome::ComparisonScopeIncomplete,
                    resolvedLocationPublicId: $location?->public_id,
                );
            }

            return new EligibleRetrievalScope(RetrievalOutcome::EvidenceFound, [
                'primary' => $primary->pluck('public_id')->all(),
                'comparison' => $comparison->pluck('public_id')->all(),
            ], resolvedLocationPublicId: $location?->public_id);
        }

        $resolved = match ($plan->temporalMode) {
            RetrievalTemporalMode::Current => $this->current($families, $evaluatedAt),
            RetrievalTemporalMode::ValidAtDate => $plan->explicitDate !== null
                ? $this->current($families, $plan->explicitDate)
                : $this->resolveEach($families, fn (DocumentFamily $family): TemporalResolution => $this->periods->resolve($family, $plan->temporalReference['value'], $plan->evidenceNeed['authority']['source']['quote'] ?? null)),
            RetrievalTemporalMode::HistoricalReference => $this->resolveEach(
                $families,
                fn (DocumentFamily $family): TemporalResolution => $this->historical->resolve(
                    $family,
                    $plan->temporalReference['value'],
                    $evaluatedAt,
                ),
            ),
            default => collect(),
        };
        if ($resolved instanceof EligibilityClarificationReason) {
            return new EligibleRetrievalScope(
                RetrievalOutcome::ClarificationRequired,
                reason: $resolved->value,
                resolvedLocationPublicId: $location?->public_id,
                clarificationSource: RetrievalClarificationSource::EligibilityResolver,
            );
        }
        $eligible = $this->eligible($resolved, $authorised, $location);
        if ($eligible->isEmpty()) {
            return new EligibleRetrievalScope(
                RetrievalOutcome::NoEligibleEvidence,
                resolvedLocationPublicId: $location?->public_id,
            );
        }

        return new EligibleRetrievalScope(RetrievalOutcome::EvidenceFound, [
            'primary' => $eligible->pluck('public_id')->all(),
        ], resolvedLocationPublicId: $location?->public_id);
    }

    /**
     * @param  Collection<int, DocumentFamily>  $families
     * @param  Collection<int, Document>  $primary
     * @return Collection<int, Document>|EligibilityClarificationReason
     */
    private function transitionNeed(
        Collection $families,
        AuthorisedKnowledgeScope $authorised,
        ?OrganisationalLocation $location,
        array $authority,
        CarbonImmutable $evaluatedAt,
    ): EligibleRetrievalScope {
        $window = $authority['kind'] === 'transition_boundary'
            ? [CarbonImmutable::parse($authority['value'])->startOfDay(), CarbonImmutable::parse($authority['value'])->addDay()->startOfDay()]
            : $this->periods->window($authority['value']);
        if ($window === null) {
            return new EligibleRetrievalScope(RetrievalOutcome::ClarificationRequired,
                reason: EligibilityClarificationReason::UnresolvableTemporalPeriod->value,
                clarificationSource: RetrievalClarificationSource::EligibilityResolver);
        }
        [$start, $end] = $window;
        $primary = collect();
        $comparison = collect();
        foreach ($families as $family) {
            $pairs = [];
            foreach ($this->timeline->attainedVersions($family) as $version) {
                $at = $this->timeline->authorityStart($version);
                if ($at === null || $at->lt($start) || $at->gte($end) || $at->gt($evaluatedAt)) {
                    continue;
                }
                $before = $this->timeline->resolve($family, $at->subMicrosecond());
                $after = $this->timeline->resolve($family, $at);
                if ($before !== null && $after !== null && ! $before->is($after)) {
                    // Scope both sides before treating a transition as available.
                    if ($this->eligible(collect([$before, $after]), $authorised, $location)->count() === 2) {
                        $pairs[] = [$after, $before];
                    }
                }
            }
            if (count($pairs) > 1) {
                return new EligibleRetrievalScope(RetrievalOutcome::ClarificationRequired,
                    reason: EligibilityClarificationReason::AmbiguousAuthorityWindowForPeriod->value,
                    resolvedLocationPublicId: $location?->public_id,
                    clarificationSource: RetrievalClarificationSource::EligibilityResolver);
            }
            if (count($pairs) === 1) {
                $primary->push($pairs[0][0]);
                $comparison->push($pairs[0][1]);
            }
        }

        return $primary->isEmpty()
            ? new EligibleRetrievalScope(RetrievalOutcome::ComparisonScopeIncomplete, resolvedLocationPublicId: $location?->public_id)
            : new EligibleRetrievalScope(RetrievalOutcome::EvidenceFound,
                ['primary' => $primary->pluck('public_id')->all(), 'comparison' => $comparison->pluck('public_id')->all()],
                resolvedLocationPublicId: $location?->public_id);
    }

    private function comparison(
        Collection $families,
        RetrievalPlan $plan,
        CarbonImmutable $evaluatedAt,
        Collection $primary,
    ): Collection|EligibilityClarificationReason {
        if ($plan->explicitDate !== null) {
            return $this->current($families, $plan->explicitDate);
        }
        if ($plan->temporalReference !== null) {
            return $this->resolveEach($families, fn (DocumentFamily $family): TemporalResolution => $plan->temporalReference['kind'] === RetrievalTemporalReferenceKind::CalendarPeriod
                    ? $this->periods->resolve($family, $plan->temporalReference['value'], $plan->evidenceNeed['authority']['source']['quote'] ?? null)
                    : $this->historical->resolve($family, $plan->temporalReference['value'], $evaluatedAt));
        }

        return $primary->map(function (Document $document): ?Document {
            $attained = $this->timeline->attainedVersions($document->family);
            $position = $attained->search(fn (Document $item): bool => $item->is($document));

            return is_int($position) && $position > 0 ? $attained->get($position - 1) : null;
        })->filter()->values();
    }

    /** @param Collection<int, DocumentFamily> $families
     * @return Collection<int, Document>
     */
    private function current(Collection $families, CarbonImmutable $at): Collection
    {
        return $families->map(
            fn (DocumentFamily $family): ?Document => $this->timeline->resolve($family, $at),
        )->filter(fn (mixed $document): bool => $document instanceof Document)->values();
    }

    /**
     * Retain only families for which both distinct authority states exist. A
     * current version is never allowed to stand in for missing history.
     *
     * @param  Collection<int, Document>  $primary
     * @param  Collection<int, Document>  $comparison
     * @return array{Collection<int, Document>, Collection<int, Document>}
     */
    private function distinctComparisonVersions(Collection $primary, Collection $comparison): array
    {
        $comparisonByFamily = $comparison->keyBy('document_family_id');
        $pairedPrimary = $primary->filter(function (Document $document) use ($comparisonByFamily): bool {
            $historical = $comparisonByFamily->get($document->document_family_id);

            return $historical instanceof Document && ! $historical->is($document);
        })->values();
        $primaryByFamily = $pairedPrimary->keyBy('document_family_id');
        $pairedComparison = $comparison->filter(function (Document $document) use ($primaryByFamily): bool {
            $current = $primaryByFamily->get($document->document_family_id);

            return $current instanceof Document && ! $current->is($document);
        })->values();

        return [$pairedPrimary, $pairedComparison];
    }

    /**
     * @param  Collection<int, DocumentFamily>  $families
     * @param  callable(DocumentFamily): TemporalResolution  $resolver
     * @return Collection<int, Document>|EligibilityClarificationReason
     */
    private function resolveEach(Collection $families, callable $resolver): Collection|EligibilityClarificationReason
    {
        $documents = collect();
        $nonContributingReason = null;
        foreach ($families as $family) {
            $resolution = $resolver($family);
            if ($resolution->reason !== null) {
                if ($nonContributingReason === null || in_array($resolution->reason, [
                    EligibilityClarificationReason::AmbiguousAuthorityWindowForPeriod,
                    EligibilityClarificationReason::AmbiguousHistoricalReference,
                ], true)) {
                    $nonContributingReason = $resolution->reason;
                }

                continue;
            }
            if ($resolution->document !== null) {
                $documents->push($resolution->document);
            }
        }

        return $documents->isEmpty() && $nonContributingReason !== null
            ? $nonContributingReason
            : $documents;
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return Collection<int, Document>
     */
    private function eligible(
        Collection $documents,
        AuthorisedKnowledgeScope $authorised,
        ?OrganisationalLocation $location,
    ): Collection {
        $generationId = $authorised->activeCorpusGeneration?->id;

        return $documents->filter(function (Document $document) use ($generationId, $location): bool {
            if ($document->status !== DocumentStatus::Indexed || $generationId === null) {
                return false;
            }
            if (! $this->appliesTo($document, $location)) {
                return false;
            }

            return $document->chunks()->whereHas(
                'workspaceCorpusGenerations',
                fn ($query) => $query->where('workspace_corpus_generations.id', $generationId),
            )->exists();
        })->values();
    }

    private function appliesTo(Document $document, ?OrganisationalLocation $location): bool
    {
        if ($location === null) {
            return true;
        }
        $snapshot = $document->applicabilitySnapshot;
        if ($snapshot === null || $snapshot->scope === DocumentApplicabilityScope::Universal) {
            return $snapshot !== null;
        }
        $ancestorIds = [$location->id];
        $parent = $location->parent;
        while ($parent !== null) {
            $ancestorIds[] = $parent->id;
            $parent = $parent->parent;
        }

        return $snapshot->locations->contains(
            fn (OrganisationalLocation $candidate): bool => in_array($candidate->id, $ancestorIds, true),
        );
    }

    /**
     * @param  list<string>  $references
     */
    private function resolveLocations(
        AuthorisedKnowledgeScope $authorised,
        array $references,
    ): ?OrganisationalLocation {
        if ($references === []) {
            return null;
        }
        $resolved = collect();
        foreach ($references as $reference) {
            if ($this->isOrganisationReference($authorised, $reference)) {
                continue;
            }
            $match = $this->resolveLocationReference($authorised, $reference);
            if ($match === null) {
                continue;
            }
            $resolved->put($match->id, $match);
        }
        if ($resolved->isEmpty()) {
            return null;
        }
        if ($resolved->count() === 1) {
            return $resolved->first();
        }

        $mostSpecific = $resolved->filter(fn (OrganisationalLocation $candidate): bool => $resolved->every(fn (OrganisationalLocation $ancestor): bool => $this->isDescendantOrSame($candidate, $ancestor)));

        return $mostSpecific->count() === 1 ? $mostSpecific->first() : null;
    }

    private function resolveLocationReference(
        AuthorisedKnowledgeScope $authorised,
        string $reference,
    ): ?OrganisationalLocation {
        $normalised = Str::lower(trim($reference));
        $matches = $this->locationMatches($authorised, $normalised);
        if ($matches->isEmpty() && str_starts_with($normalised, 'the ')) {
            $withoutArticle = trim(substr($normalised, 4));
            if (! in_array($withoutArticle, ['home', 'site', 'office', 'region', 'location'], true)) {
                $matches = $this->locationMatches($authorised, $withoutArticle);
            }
        }
        if ($matches->isEmpty()) {
            $withoutScopeSuffix = preg_replace(
                '/\s+(?:outreach|services?|teams?|sites?|homes?|offices?|regions?)$/u',
                '',
                $normalised,
            );
            if (is_string($withoutScopeSuffix) && $withoutScopeSuffix !== $normalised) {
                $matches = $this->locationMatches($authorised, $withoutScopeSuffix);
            }
        }
        if ($matches->isEmpty()) {
            $matches = (new LocationReferenceCandidates)->shortened($authorised->workspace->id, $normalised);
        }

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return Collection<int, OrganisationalLocation> */
    private function locationMatches(AuthorisedKnowledgeScope $authorised, string $normalised): Collection
    {
        return OrganisationalLocation::query()
            ->where('workspace_id', $authorised->workspace->id)
            ->where(function ($query) use ($normalised): void {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [$normalised])
                    ->orWhereHas('aliases', fn ($aliases) => $aliases->where('normalised_alias', $normalised));
            })->get();
    }

    private function isOrganisationReference(
        AuthorisedKnowledgeScope $authorised,
        string $reference,
    ): bool {
        $reference = $this->normaliseIdentity($reference);
        $organisation = $this->normaliseIdentity($authorised->workspace->name);
        if ($reference === $organisation) {
            return true;
        }

        $genericSuffixes = [
            'care', 'group', 'organisation', 'organization', 'services',
            'limited', 'ltd', 'plc', 'incorporated', 'inc',
        ];
        $parts = explode(' ', $organisation);
        while ($parts !== [] && in_array(end($parts), $genericSuffixes, true)) {
            array_pop($parts);
        }

        return $parts !== [] && $reference === implode(' ', $parts);
    }

    private function normaliseIdentity(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->value();
    }

    private function isDescendantOrSame(
        OrganisationalLocation $candidate,
        OrganisationalLocation $ancestor,
    ): bool {
        $current = $candidate;
        while (true) {
            if ($current->is($ancestor)) {
                return true;
            }
            if ($current->parent === null) {
                return false;
            }
            $current = $current->parent;
        }
    }
}
