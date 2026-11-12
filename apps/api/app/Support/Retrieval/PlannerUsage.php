<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\PlannerCostBasis;
use App\Support\Generation\ValidationAttemptUsage;
use InvalidArgumentException;

final readonly class PlannerUsage
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $execution,
        public int $requestCount,
        public int $retryCount,
        public ?int $inputTokens,
        public ?int $cachedInputTokens,
        public ?int $outputTokens,
        public float $latencyMs,
        public PlannerCostBasis $costBasis,
        public ?float $costUsd,
        public ?string $pricingSnapshot,
        public array $validationAttempts = [],
    ) {}

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $basis = PlannerCostBasis::tryFrom((string) ($value['cost_basis'] ?? ''));
        if (
            ! is_string($value['provider'] ?? null)
            || ! is_string($value['model'] ?? null)
            || ($value['stage'] ?? null) !== 'planner'
            || ! in_array($value['execution'] ?? null, ['provider_api', 'local'], true)
            || ! is_int($value['request_count'] ?? null)
            || $value['request_count'] < 0
            || ! is_int($value['retry_count'] ?? null)
            || $value['retry_count'] < 0
            || ! is_numeric($value['latency_ms'] ?? null)
            || $value['latency_ms'] < 0
            || $basis === null
        ) {
            throw new InvalidArgumentException('The planner usage observation is invalid.');
        }
        foreach (['input_tokens', 'cached_input_tokens', 'output_tokens'] as $key) {
            if (($value[$key] ?? null) !== null && (! is_int($value[$key]) || $value[$key] < 0)) {
                throw new InvalidArgumentException('The planner token usage is invalid.');
            }
        }
        $cost = $value['cost_usd'] ?? null;
        $pricing = $value['pricing_snapshot'] ?? null;
        if (($cost !== null && (! is_numeric($cost) || $cost < 0))
            || ($pricing !== null && ! is_string($pricing))
            || ($basis === PlannerCostBasis::Unavailable && $cost !== null)
            || ($basis === PlannerCostBasis::ZeroCostLocal
                && ($cost === null || (float) $cost !== 0.0
                    || ! in_array($value['execution'], ['local'], true)))
            || ($basis === PlannerCostBasis::Estimated && ($cost === null || $pricing === null))
            || ($basis === PlannerCostBasis::ProviderReported && $cost === null)) {
            throw new InvalidArgumentException('The planner cost observation is invalid.');
        }

        return new self(
            $value['provider'],
            $value['model'],
            $value['execution'],
            $value['request_count'],
            $value['retry_count'],
            $value['input_tokens'] ?? null,
            $value['cached_input_tokens'] ?? null,
            $value['output_tokens'] ?? null,
            (float) $value['latency_ms'],
            $basis,
            $cost === null ? null : (float) $cost,
            $pricing,
            self::safeAttempts($value['validation_attempts'] ?? []),
        );
    }

    public static function safeValidation(mixed $candidate): ?array
    {
        $validation = null;
        if (is_array($candidate)
            && in_array($candidate['stage'] ?? null, ['completion', 'envelope', 'schema', 'grounding', 'catalogue', 'interpretation'], true)
            && in_array($candidate['code'] ?? null, ['binding_coverage', 'unknown_family', 'ownership_grounding',
                'ownership_location_conflict', 'reference_grounding', 'calendar_grounding', 'temporal_relation_grounding', 'need_order',
                'fact_date_precision', 'fact_date_interval_shape', 'fact_date_interval_reversed', 'fact_date_calendar',
                'historical_grounding', 'binding_shape', 'comparison_authority', 'authority_grounding',
                'authority_precision', 'scope_question_required', 'scope_question_misplaced', 'scope_claim_conflict',
                'clause_omitted', 'meaning_changed', 'scope_added', 'interpretation_check_unavailable',
                'invalid_value', 'missing', 'literal_error', 'uuid_parsing',
                'extra_forbidden', 'json_invalid', 'string_type', 'too_short', 'too_long', 'value_error', 'output_truncated', 'output_not_completed'], true)
            && is_string($candidate['field'] ?? null)) {
            $allowed = ['root', 'unknown', 'needs', 'bindings', 'need_id', 'authority', 'kind', 'value', 'precision',
                'source', 'question_span', 'applicability', 'fact_reference', 'claim_under_test', 'scope_question',
                'relation', 'supports_need_id', 'family_ids',
                'subject_status', 'ownership', 'ownership_source', 'turn_id', 'quote', 'operation',
                'schema_version', 'decision', 'clarification', 'aspect', 'central'];
            $parts = explode('.', $candidate['field']);
            if (count($parts) <= 6 && array_all($parts, fn ($part) => in_array($part, $allowed, true)
                || in_array($part, array_map('strval', range(0, 20)), true))) {
                $validation = array_intersect_key($candidate, array_flip(['stage', 'field', 'code']));
            }
        }

        return $validation;
    }

    public static function safeAttempts(mixed $attempts): array
    {
        return array_map(static function ($attempt) {
            $attempt = is_array($attempt) ? $attempt : [];

            return [...ValidationAttemptUsage::receipt($attempt),
                ...(isset($attempt['interpretation_check']) ? ['interpretation_check' => self::safeInterpretationCheck($attempt['interpretation_check'])] : []),
                'validation' => self::safeValidation($attempt['validation'] ?? null),
                'status' => in_array($attempt['status'] ?? null, ['accepted', 'rejected'], true) ? $attempt['status'] : 'unknown'];
        }, is_array($attempts) ? array_slice($attempts, 0, 2) : []);
    }

    private static function safeInterpretationCheck(mixed $check): ?array
    {
        if (! is_array($check) || ! in_array($check['version'] ?? null, ['interpretation-check-v1', 'interpretation-check-v2'], true)
            || ! in_array($check['code'] ?? null, ['accepted', 'clause_omitted', 'meaning_changed', 'scope_added', 'unavailable'], true)) {
            return null;
        }

        $plan = $check['reviewed_plan'] ?? null;
        if (is_array($plan) && strlen(json_encode($plan, JSON_THROW_ON_ERROR)) <= 100000) {
            try {
                EvidenceNeedsPlan::validateWireShape($plan, 'plan');
            } catch (InvalidArgumentException) {
                $plan = null;
            }
        } else {
            $plan = null;
        }

        $verification = [];
        if ($check['version'] === 'interpretation-check-v2') {
            $citation = is_array($check['citation'] ?? null) ? $check['citation'] : null;
            if ($citation !== null) {
                $bounded = [];
                foreach (['user_turn_id' => 100, 'user_quote' => 1000, 'need_id' => 10, 'plan_field' => 100, 'plan_quote' => 1000] as $field => $limit) {
                    $bounded[$field] = is_string($citation[$field] ?? null) ? mb_substr($citation[$field], 0, $limit) : null;
                }
                $bounded['inspected_need_ids'] = array_map(fn ($id) => is_string($id) ? mb_substr($id, 0, 10) : null,
                    array_slice(is_array($citation['inspected_need_ids'] ?? null) ? $citation['inspected_need_ids'] : [], 0, 3));
                $citation = $bounded;
            }
            $verification = [
                'checker_code' => in_array($check['checker_code'] ?? null, ['accepted', 'clause_omitted', 'meaning_changed', 'scope_added', 'unavailable'], true) ? $check['checker_code'] : 'unavailable',
                'citation' => $citation,
                'citation_verification' => in_array($check['citation_verification'] ?? null, ['verified', 'unverifiable', 'not_applicable'], true) ? $check['citation_verification'] : 'unverifiable',
                'verification_reason' => in_array($check['verification_reason'] ?? null, ['missing_citation', 'user_quote_not_found', 'inspected_needs_mismatch', 'unknown_need', 'plan_quote_not_found', 'plan_quote_in_user_turns', 'application_grounded_period'], true) ? $check['verification_reason'] : null,
            ];
        }

        return [...ValidationAttemptUsage::receipt($check), ...$verification,
            'reviewed_plan' => $plan,
            'version' => $check['version'], 'code' => $check['code'],
            'provider' => is_string($check['provider'] ?? null) ? substr($check['provider'], 0, 100) : null,
            'model' => is_string($check['model'] ?? null) ? substr($check['model'], 0, 100) : null];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stage' => 'planner',
            'provider' => $this->provider,
            'model' => $this->model,
            'execution' => strtoupper($this->execution),
            'request_count' => $this->requestCount,
            'retry_count' => $this->retryCount,
            'input_tokens' => $this->inputTokens,
            'cached_input_tokens' => $this->cachedInputTokens,
            'output_tokens' => $this->outputTokens,
            'latency_ms' => $this->latencyMs,
            'cost_basis' => strtoupper($this->costBasis->value),
            'cost_usd' => $this->costUsd,
            'pricing_snapshot' => $this->pricingSnapshot,
            ...($this->validationAttempts === [] ? [] : ['validation_attempts' => $this->validationAttempts]),
        ];
    }
}
