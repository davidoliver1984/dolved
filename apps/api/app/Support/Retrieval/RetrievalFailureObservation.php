<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use InvalidArgumentException;

final readonly class RetrievalFailureObservation
{
    private const STAGES = [
        'dense_embedding', 'qdrant_dense_search', 'sparse_encoding',
        'qdrant_sparse_search', 'fusion', 'reranker', 'threshold',
        'final_eligibility', 'transport_orchestration',
    ];

    private const EXECUTIONS = ['provider_api', 'local', 'infrastructure', 'orchestration'];

    private const CATEGORIES = [
        'timeout', 'rate_limited', 'provider_http_error', 'connection_error',
        'invalid_provider_response', 'contract_validation_error',
        'local_execution_error', 'infrastructure_error', 'unknown',
    ];

    /** @param list<array<string, mixed>> $usage */
    public function __construct(
        public string $stage,
        public string $execution,
        public ?string $provider,
        public ?string $model,
        public string $category,
        public ?int $httpStatus,
        public ?int $retryCount,
        public ?int $requestCount,
        public float $latencyMs,
        public array $usage,
        public bool $downstreamRequestAttempted,
        public bool $candidateLineageProduced,
        public ?int $providerRetryCount = null,
        public ?int $outerRetryCount = null,
        public ?string $firstFailureAt = null,
        public ?string $finalFailureAt = null,
        public ?float $retryDelayMs = null,
        public ?float $providerRetryAfterSeconds = null,
        public ?string $providerTimingSource = null,
        public ?int $rateLimitEventCount = null,
        public array $retryDelays = [],
    ) {
        if (
            ! in_array($stage, self::STAGES, true)
            || ! in_array($execution, self::EXECUTIONS, true)
            || ! in_array($category, self::CATEGORIES, true)
            || ($httpStatus !== null && ($httpStatus < 100 || $httpStatus > 599))
            || ($retryCount !== null && $retryCount < 0)
            || ($requestCount !== null && $requestCount < 0)
            || ($providerRetryCount !== null && $providerRetryCount < 0)
            || ($outerRetryCount !== null && $outerRetryCount < 0)
            || ($retryDelayMs !== null && $retryDelayMs < 0)
            || ($providerRetryAfterSeconds !== null && $providerRetryAfterSeconds < 0)
            || ($rateLimitEventCount !== null && $rateLimitEventCount < 0)
            || $latencyMs < 0
        ) {
            throw new InvalidArgumentException('The retrieval failure observation is invalid.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(
            self::requiredString($value, 'stage'),
            self::requiredString($value, 'execution'),
            self::nullableString($value, 'provider'),
            self::nullableString($value, 'model'),
            self::requiredString($value, 'category'),
            self::nullableInt($value, 'http_status'),
            self::nullableInt($value, 'retry_count'),
            self::nullableInt($value, 'request_count'),
            is_numeric($value['latency_ms'] ?? null) ? (float) $value['latency_ms'] : -1,
            is_array($value['usage'] ?? null) ? array_values($value['usage']) : [],
            ($value['downstream_request_attempted'] ?? null) === true,
            ($value['candidate_lineage_produced'] ?? null) === true,
            self::nullableInt($value, 'provider_retry_count'),
            self::nullableInt($value, 'outer_retry_count'),
            self::nullableString($value, 'first_failure_at'),
            self::nullableString($value, 'final_failure_at'),
            self::nullableFloat($value, 'retry_delay_ms'),
            self::nullableFloat($value, 'provider_retry_after_seconds'),
            self::nullableString($value, 'provider_timing_source'),
            self::nullableInt($value, 'rate_limit_event_count'),
            is_array($value['retry_delays'] ?? null) ? array_values($value['retry_delays']) : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'execution' => $this->execution,
            'provider' => $this->provider,
            'model' => $this->model,
            'category' => $this->category,
            'http_status' => $this->httpStatus,
            'retry_count' => $this->retryCount,
            'request_count' => $this->requestCount,
            'latency_ms' => $this->latencyMs,
            'usage' => $this->usage,
            'downstream_request_attempted' => $this->downstreamRequestAttempted,
            'candidate_lineage_produced' => $this->candidateLineageProduced,
            'provider_retry_count' => $this->providerRetryCount,
            'outer_retry_count' => $this->outerRetryCount,
            'first_failure_at' => $this->firstFailureAt,
            'final_failure_at' => $this->finalFailureAt,
            'retry_delay_ms' => $this->retryDelayMs,
            'provider_retry_after_seconds' => $this->providerRetryAfterSeconds,
            'provider_timing_source' => $this->providerTimingSource,
            'rate_limit_event_count' => $this->rateLimitEventCount,
            'retry_delays' => $this->retryDelays,
        ];
    }

    private static function requiredString(array $value, string $key): string
    {
        return is_string($value[$key] ?? null) ? $value[$key] : '';
    }

    private static function nullableString(array $value, string $key): ?string
    {
        return is_string($value[$key] ?? null) ? $value[$key] : null;
    }

    private static function nullableInt(array $value, string $key): ?int
    {
        return is_int($value[$key] ?? null) ? $value[$key] : null;
    }

    private static function nullableFloat(array $value, string $key): ?float
    {
        return is_numeric($value[$key] ?? null) ? (float) $value[$key] : null;
    }
}
