<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\DocumentFamily;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class DocumentAuthorityTimeline
{
    public function authorityStart(Document $document): ?CarbonImmutable
    {
        if ($document->approved_at === null) {
            return null;
        }

        $effectiveFrom = CarbonImmutable::instance($document->effective_from);
        $approvedAt = CarbonImmutable::instance($document->approved_at);

        return $effectiveFrom->greaterThan($approvedAt) ? $effectiveFrom : $approvedAt;
    }

    /** @return Collection<int, Document> */
    public function attainedVersions(DocumentFamily $family): Collection
    {
        $versions = $family->documents()->get();
        $ordered = $this->lineageOrder($versions);
        $attainedSuccessors = collect();

        foreach ($ordered->reverse() as $version) {
            if (! in_array($version->governance_status, [
                DocumentGovernanceStatus::Approved,
                DocumentGovernanceStatus::Withdrawn,
            ], true)) {
                continue;
            }

            $authorityStart = $this->authorityStart($version);

            if ($authorityStart === null) {
                continue;
            }

            if ($version->withdrawn_at !== null && $version->withdrawn_at->lt($authorityStart)) {
                continue;
            }

            $supersededBeforeAttainment = $attainedSuccessors->contains(
                fn (Document $successor): bool => $this->authorityStart($successor)?->lte($authorityStart) === true,
            );

            if (! $supersededBeforeAttainment) {
                $attainedSuccessors->prepend($version);
            }
        }

        return $attainedSuccessors->values();
    }

    public function resolve(DocumentFamily $family, CarbonInterface $validAt): ?Document
    {
        $at = CarbonImmutable::instance($validAt);
        $attained = $this->attainedVersions($family);

        foreach ($attained as $index => $version) {
            $start = $this->authorityStart($version);
            $nextStart = isset($attained[$index + 1])
                ? $this->authorityStart($attained[$index + 1])
                : null;
            $end = $version->withdrawn_at === null
                ? $nextStart
                : ($nextStart === null || $version->withdrawn_at->lt($nextStart)
                    ? CarbonImmutable::instance($version->withdrawn_at)
                    : $nextStart);

            if ($start !== null && $at->gte($start) && ($end === null || $at->lt($end))) {
                return $version;
            }
        }

        return null;
    }

    /** @param Collection<int, Document> $versions */
    public function assertConsistent(Collection $versions): void
    {
        $ordered = $this->lineageOrder($versions);
        $effectiveDates = [];
        $authorityStarts = [];
        $lastAttainableStart = null;

        foreach ($ordered as $version) {
            if ($version->approved_at === null) {
                continue;
            }

            $effectiveKey = $version->effective_from->toIso8601String();
            $start = $this->authorityStart($version);
            $startKey = $start?->toIso8601String();

            if (isset($effectiveDates[$effectiveKey]) || ($startKey !== null && isset($authorityStarts[$startKey]))) {
                throw new DocumentGovernanceException('Approved versions require unique effective and authority-start timestamps.');
            }

            $cancelledBeforeAttainment = $start !== null
                && $version->withdrawn_at !== null
                && $version->withdrawn_at->lt($start);

            if (! $cancelledBeforeAttainment && $start !== null && $lastAttainableStart !== null && $start->lte($lastAttainableStart)) {
                throw new DocumentGovernanceException('An approved successor must attain authority after its predecessor.');
            }

            $effectiveDates[$effectiveKey] = true;
            if ($startKey !== null) {
                $authorityStarts[$startKey] = true;
            }
            if (! $cancelledBeforeAttainment) {
                $lastAttainableStart = $start;
            }
        }
    }

    /** @param Collection<int, Document> $versions
     * @return Collection<int, Document>
     */
    private function lineageOrder(Collection $versions): Collection
    {
        if ($versions->isEmpty()) {
            return collect();
        }

        $root = $versions->firstWhere('predecessor_document_id', null);
        if (! $root instanceof Document) {
            throw new DocumentGovernanceException('A document family must have one lineage root.');
        }

        $ordered = collect([$root]);
        $current = $root;

        while (($next = $versions->firstWhere('predecessor_document_id', $current->id)) instanceof Document) {
            if ($ordered->contains('id', $next->id)) {
                throw new DocumentGovernanceException('A document family lineage cannot contain a cycle.');
            }
            $ordered->push($next);
            $current = $next;
        }

        if ($ordered->count() !== $versions->count()) {
            throw new DocumentGovernanceException('A document family must contain one linear lineage.');
        }

        return $ordered;
    }
}
