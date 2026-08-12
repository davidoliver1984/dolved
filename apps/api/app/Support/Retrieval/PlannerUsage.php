<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\PlannerCostBasis;
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
        );
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
        ];
    }
}
