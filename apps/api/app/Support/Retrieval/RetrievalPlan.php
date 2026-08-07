<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\RetrievalTemporalMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class RetrievalPlan
{
    /**
     * @param  array{kind: string, at?: string|null}|null  $primaryAnchor
     * @param  array{kind: string, at?: string|null}|null  $comparisonAnchor
     */
    public function __construct(
        public string $query,
        public RetrievalTemporalMode $temporalMode,
        public ?CarbonImmutable $validAt,
        public ?array $primaryAnchor,
        public ?array $comparisonAnchor,
        public ?string $applicabilityReference,
        public ?string $clarificationReason,
    ) {}

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value, string $originalQuestion): self
    {
        $queries = $value['retrieval_queries'] ?? null;
        if (! is_array($queries) || count($queries) !== 1 || $queries[0] !== $originalQuestion) {
            throw new InvalidArgumentException('The planner must preserve exactly one V1 retrieval query.');
        }

        $mode = RetrievalTemporalMode::tryFrom((string) ($value['temporal_mode'] ?? ''));
        if ($mode === null) {
            throw new InvalidArgumentException('The planner returned an unsupported temporal mode.');
        }

        $validAt = isset($value['valid_at']) && is_string($value['valid_at'])
            ? CarbonImmutable::parse($value['valid_at'])
            : null;
        $primary = isset($value['primary_anchor']) && is_array($value['primary_anchor'])
            ? $value['primary_anchor'] : null;
        $comparison = isset($value['comparison_anchor']) && is_array($value['comparison_anchor'])
            ? $value['comparison_anchor'] : null;

        if (($mode === RetrievalTemporalMode::ValidAtDate) !== ($validAt !== null)) {
            throw new InvalidArgumentException('The plan has inconsistent valid-at fields.');
        }
        if (($mode === RetrievalTemporalMode::Compare) !== ($primary !== null && $comparison !== null)) {
            throw new InvalidArgumentException('The plan has inconsistent comparison fields.');
        }

        return new self(
            $originalQuestion,
            $mode,
            $validAt,
            $primary,
            $comparison,
            is_string($value['applicability_reference'] ?? null)
                ? trim($value['applicability_reference']) : null,
            is_string($value['clarification_reason'] ?? null)
                ? $value['clarification_reason'] : null,
        );
    }
}
