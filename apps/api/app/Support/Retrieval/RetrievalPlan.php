<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\PlannerClarificationReason;
use App\Enums\RequestedEvidenceType;
use App\Enums\RetrievalTemporalMode;
use App\Enums\RetrievalTemporalReferenceKind;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class RetrievalPlan
{
    /**
     * @param  array{kind: RetrievalTemporalReferenceKind, value: string}|null  $temporalReference
     * @param  array{kind: RetrievalTemporalReferenceKind, value: string}|null  $versionTransitionBoundary
     * @param  list<string>  $locationReferences
     */
    public function __construct(
        public string $query,
        public RetrievalTemporalMode $temporalMode,
        public ?CarbonImmutable $explicitDate,
        public ?array $temporalReference,
        public array $locationReferences,
        public ?PlannerClarificationReason $clarificationReason,
        public ClassifierLineage $classifierLineage,
        public PlannerUsage $classifierUsage,
        public ?array $versionTransitionBoundary = null,
        public ?string $factDate = null,
        public RequestedEvidenceType $requestedEvidenceType = RequestedEvidenceType::PolicyOrProceduralRequirements,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $lineage
     * @param  array<string, mixed>  $usage
     */
    public static function fromArray(array $value, string $originalQuestion, array $lineage, array $usage): self
    {
        $queries = $value['retrieval_queries'] ?? null;
        if (! is_array($queries) || count($queries) !== 1 || $queries[0] !== $originalQuestion) {
            throw new InvalidArgumentException('The planner must preserve exactly one V1 retrieval query.');
        }

        $mode = RetrievalTemporalMode::tryFrom((string) ($value['temporal_mode'] ?? ''));
        if ($mode === null) {
            throw new InvalidArgumentException('The planner returned an unsupported temporal mode.');
        }

        $explicitDate = self::date($value['explicit_date'] ?? null);
        $reference = self::reference($value['temporal_reference'] ?? null);
        $transitionBoundary = self::reference($value['version_transition_boundary'] ?? null);
        $factDate = self::boundedReference($value['fact_date'] ?? null);
        $requestedEvidenceType = RequestedEvidenceType::tryFrom((string) ($value['requested_evidence_type'] ?? ''))
            ?? ($mode === RetrievalTemporalMode::Compare
                ? RequestedEvidenceType::CurrentVersusHistoricalComparison
                : RequestedEvidenceType::PolicyOrProceduralRequirements);
        $locations = self::locations($value['location_references'] ?? null);
        $reasonValue = $value['clarification_reason'] ?? null;
        $reason = is_string($reasonValue) ? PlannerClarificationReason::tryFrom($reasonValue) : null;
        if ($reasonValue !== null && $reason === null) {
            throw new InvalidArgumentException('The planner returned an unsupported clarification reason.');
        }

        self::assertConsistent(
            $mode,
            $explicitDate,
            $reference,
            $transitionBoundary,
            $factDate,
            $requestedEvidenceType,
            $reason,
        );

        return new self(
            $originalQuestion,
            $mode,
            $explicitDate,
            $reference,
            $locations,
            $reason,
            ClassifierLineage::fromArray($lineage),
            PlannerUsage::fromArray($usage),
            $transitionBoundary,
            $factDate,
            $requestedEvidenceType,
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('The planner explicit date must be a date string.');
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('The planner explicit date must use YYYY-MM-DD.');
        }

        return $date;
    }

    /** @return array{kind: RetrievalTemporalReferenceKind, value: string}|null */
    private static function reference(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException('The planner temporal reference must be typed.');
        }
        $kind = RetrievalTemporalReferenceKind::tryFrom((string) ($value['kind'] ?? ''));
        $reference = $value['value'] ?? null;
        if ($kind === null || ! is_string($reference) || trim($reference) === '' || mb_strlen($reference) > 255) {
            throw new InvalidArgumentException('The planner temporal reference is invalid.');
        }

        return ['kind' => $kind, 'value' => trim($reference)];
    }

    /** @return list<string> */
    private static function locations(mixed $value): array
    {
        if (! is_array($value) || count($value) > 8) {
            throw new InvalidArgumentException('The planner location references are invalid.');
        }
        $locations = [];
        foreach ($value as $reference) {
            if (! is_string($reference) || trim($reference) === '' || mb_strlen($reference) > 255) {
                throw new InvalidArgumentException('The planner location references are invalid.');
            }
            $locations[] = trim($reference);
        }

        return $locations;
    }

    private static function boundedReference(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 255) {
            throw new InvalidArgumentException('The planner fact date is invalid.');
        }

        return trim($value);
    }

    /** @param array{kind: RetrievalTemporalReferenceKind, value: string}|null $reference @param array{kind: RetrievalTemporalReferenceKind, value: string}|null $transitionBoundary */
    private static function assertConsistent(
        RetrievalTemporalMode $mode,
        ?CarbonImmutable $date,
        ?array $reference,
        ?array $transitionBoundary,
        ?string $factDate,
        RequestedEvidenceType $requestedEvidenceType,
        ?PlannerClarificationReason $reason,
    ): void {
        if ($date !== null && $reference !== null) {
            throw new InvalidArgumentException('The planner temporal selectors are mutually exclusive.');
        }
        if ($transitionBoundary !== null && (
            $mode !== RetrievalTemporalMode::Compare
            || $transitionBoundary['kind'] !== RetrievalTemporalReferenceKind::CalendarPeriod
            || $date !== null
            || $reference !== null
        )) {
            throw new InvalidArgumentException('A version transition boundary is an independent comparison selector.');
        }
        if ($requestedEvidenceType === RequestedEvidenceType::CurrentVersusHistoricalComparison
            && $mode !== RetrievalTemporalMode::Compare) {
            throw new InvalidArgumentException('Comparison evidence requires a comparison plan.');
        }
        if ($requestedEvidenceType === RequestedEvidenceType::PersonalRecordStatus
            && ($date !== null || $reference !== null)) {
            throw new InvalidArgumentException('A personal fact date cannot become a document-authority selector.');
        }
        if ($factDate !== null && $requestedEvidenceType !== RequestedEvidenceType::PersonalRecordStatus) {
            throw new InvalidArgumentException('A fact date requires a personal-record claim type.');
        }
        if ($mode === RetrievalTemporalMode::ValidAtDate && (
            ($date === null) === ($reference === null)
            || ($reference !== null && $reference['kind'] !== RetrievalTemporalReferenceKind::CalendarPeriod)
        )) {
            throw new InvalidArgumentException('A valid-at plan requires one exact date or calendar period.');
        }
        if ($mode === RetrievalTemporalMode::HistoricalReference && (
            $date !== null
            || $reference === null
            || $reference['kind'] !== RetrievalTemporalReferenceKind::HistoricalReference
        )) {
            throw new InvalidArgumentException('A historical plan requires one historical reference.');
        }
        if (in_array($mode, [RetrievalTemporalMode::Current, RetrievalTemporalMode::ClarificationRequired], true)
            && ($date !== null || $reference !== null)) {
            throw new InvalidArgumentException('The temporal mode forbids temporal selectors.');
        }
        if (($mode === RetrievalTemporalMode::ClarificationRequired) !== ($reason !== null)) {
            throw new InvalidArgumentException('The clarification reason is inconsistent with the temporal mode.');
        }
    }
}
