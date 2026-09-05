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
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\EligibleRetrievalScope;
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

        $location = $this->resolveLocations($authorised, $plan->locationReferences);
        if ($location instanceof EligibilityClarificationReason) {
            return new EligibleRetrievalScope(
                RetrievalOutcome::ClarificationRequired,
                reason: $location->value,
                clarificationSource: RetrievalClarificationSource::EligibilityResolver,
            );
        }

        $families = DocumentFamily::query()
            ->where('workspace_id', $authorised->workspace->id)
            ->with(['documents.applicabilitySnapshot.locations'])
            ->get();

        if ($plan->temporalMode === RetrievalTemporalMode::Compare) {
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
                : $this->resolveEach($families, fn (DocumentFamily $family): TemporalResolution => $this->periods->resolve($family, $plan->temporalReference['value'])),
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
                    ? $this->periods->resolve($family, $plan->temporalReference['value'])
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
    ): OrganisationalLocation|EligibilityClarificationReason|null {
        if ($references === []) {
            return null;
        }
        $resolved = collect();
        foreach ($references as $reference) {
            if ($this->isOrganisationReference($authorised, $reference)) {
                continue;
            }
            $match = $this->resolveLocationReference($authorised, $reference);
            if ($match instanceof EligibilityClarificationReason) {
                return $match;
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

        return $mostSpecific->count() === 1
            ? $mostSpecific->first()
            : EligibilityClarificationReason::MultipleUnrelatedLocationReferences;
    }

    private function resolveLocationReference(
        AuthorisedKnowledgeScope $authorised,
        string $reference,
    ): OrganisationalLocation|EligibilityClarificationReason {
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
                '/\s+(?:outreach|service|team|site|home|office|region)$/u',
                '',
                $normalised,
            );
            if (is_string($withoutScopeSuffix) && $withoutScopeSuffix !== $normalised) {
                $matches = $this->locationMatches($authorised, $withoutScopeSuffix);
            }
        }
        if ($matches->isEmpty()) {
            return EligibilityClarificationReason::UnresolvedLocationReference;
        }
        if ($matches->count() > 1) {
            return EligibilityClarificationReason::AmbiguousLocationReference;
        }

        return $matches->first();
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
