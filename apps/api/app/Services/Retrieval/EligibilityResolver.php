<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\DocumentApplicabilityScope;
use App\Enums\DocumentStatus;
use App\Enums\RetrievalOutcome;
use App\Enums\RetrievalTemporalMode;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\OrganisationalLocation;
use App\Support\Documents\DocumentAuthorityTimeline;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\EligibleRetrievalScope;
use App\Support\Retrieval\RetrievalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class EligibilityResolver
{
    public function __construct(private DocumentAuthorityTimeline $timeline) {}

    public function handle(
        AuthorisedKnowledgeScope $authorised,
        RetrievalPlan $plan,
        CarbonImmutable $evaluatedAt,
    ): EligibleRetrievalScope {
        if ($plan->temporalMode === RetrievalTemporalMode::ClarificationRequired) {
            return new EligibleRetrievalScope(
                RetrievalOutcome::ClarificationRequired,
                reason: $plan->clarificationReason,
            );
        }

        if ($authorised->activeCorpusGeneration === null) {
            return new EligibleRetrievalScope(RetrievalOutcome::NoEligibleEvidence);
        }

        $location = $this->resolveLocation($authorised, $plan);
        if (is_string($location)) {
            return new EligibleRetrievalScope(
                RetrievalOutcome::ClarificationRequired,
                reason: $location,
            );
        }

        $families = DocumentFamily::query()
            ->where('workspace_id', $authorised->workspace->id)
            ->with(['documents.applicabilitySnapshot.locations'])
            ->get();

        if ($plan->temporalMode === RetrievalTemporalMode::Compare) {
            $primary = $this->resolveAnchor(
                $families,
                $plan->primaryAnchor ?? [],
                $evaluatedAt,
            );
            $comparison = $this->resolveAnchor(
                $families,
                $plan->comparisonAnchor ?? [],
                $evaluatedAt,
                $primary,
            );
            $primary = $this->eligible($primary, $authorised, $location);
            $comparison = $this->eligible($comparison, $authorised, $location);
            if ($primary->isEmpty() || $comparison->isEmpty()) {
                return new EligibleRetrievalScope(
                    RetrievalOutcome::ComparisonScopeIncomplete,
                );
            }

            return new EligibleRetrievalScope(RetrievalOutcome::EvidenceFound, [
                'primary' => $primary->pluck('public_id')->all(),
                'comparison' => $comparison->pluck('public_id')->all(),
            ]);
        }

        $at = $plan->temporalMode === RetrievalTemporalMode::ValidAtDate
            ? $plan->validAt
            : $evaluatedAt;
        if ($at === null) {
            return new EligibleRetrievalScope(RetrievalOutcome::TemporalScopeUnresolved);
        }
        $resolved = $families
            ->map(fn (DocumentFamily $family): ?Document => $this->timeline->resolve($family, $at))
            ->filter(fn (mixed $document): bool => $document instanceof Document)
            ->values();
        $eligible = $this->eligible($resolved, $authorised, $location);
        if ($eligible->isEmpty()) {
            return new EligibleRetrievalScope(RetrievalOutcome::NoEligibleEvidence);
        }

        return new EligibleRetrievalScope(RetrievalOutcome::EvidenceFound, [
            'primary' => $eligible->pluck('public_id')->all(),
        ]);
    }

    /**
     * @param  Collection<int, DocumentFamily>  $families
     * @param  array<string, mixed>  $anchor
     * @param  Collection<int, Document>|null  $primary
     * @return Collection<int, Document>
     */
    private function resolveAnchor(
        Collection $families,
        array $anchor,
        CarbonImmutable $evaluatedAt,
        ?Collection $primary = null,
    ): Collection {
        $kind = $anchor['kind'] ?? null;
        if ($kind === 'previous') {
            if ($primary === null) {
                return collect();
            }

            return $primary->map(function (Document $document): ?Document {
                $attained = $this->timeline->attainedVersions($document->family);
                $position = $attained->search(fn (Document $item): bool => $item->is($document));

                return is_int($position) && $position > 0 ? $attained[$position - 1] : null;
            })->filter()->values();
        }

        $at = match ($kind) {
            'current' => $evaluatedAt,
            'at_date' => isset($anchor['at']) && is_string($anchor['at'])
                ? CarbonImmutable::parse($anchor['at']) : null,
            default => null,
        };
        if ($at === null) {
            return collect();
        }

        return $families
            ->map(fn (DocumentFamily $family): ?Document => $this->timeline->resolve($family, $at))
            ->filter(fn (mixed $document): bool => $document instanceof Document)
            ->values();
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

    private function resolveLocation(
        AuthorisedKnowledgeScope $authorised,
        RetrievalPlan $plan,
    ): OrganisationalLocation|string|null {
        if ($plan->applicabilityReference === null) {
            return null;
        }
        $normalised = Str::lower(trim($plan->applicabilityReference));
        $matches = OrganisationalLocation::query()
            ->where('workspace_id', $authorised->workspace->id)
            ->where(function ($query) use ($normalised): void {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [$normalised])
                    ->orWhereHas('aliases', fn ($aliases) => $aliases->where('normalised_alias', $normalised));
            })
            ->get();
        if ($matches->isEmpty()) {
            return 'unresolved_applicability_reference';
        }
        if ($matches->count() > 1) {
            return 'ambiguous_applicability_reference';
        }

        return $matches->first();
    }
}
