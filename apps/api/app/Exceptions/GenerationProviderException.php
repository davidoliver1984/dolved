<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Generation\GenerationValidationDiagnostics;
use App\Support\Generation\ValidationAttemptUsage;

final class GenerationProviderException extends GenerationException
{
    public function __construct(
        public readonly string $category,
        public readonly ?string $provider,
        public readonly ?string $model,
        public readonly ?int $httpStatus,
        public readonly int $attemptCount,
        public readonly float $latencyMs,
        public readonly mixed $validation = null,
        public readonly bool $returnedOutput = false,
        public readonly ?array $providerUsage = null,
    ) {
        parent::__construct('The generation provider failed with a typed operational error.');
    }

    /** Safe static diagnostics only; never exception messages or response content. */
    public function safeDiagnostics(): array
    {
        return [
            'category' => in_array($this->category, ['transport_failure', 'rate_limit', 'timeout', 'malformed_typed_output', 'provider_refusal', 'contract_validation_failure'], true)
                ? $this->category : 'unknown_generation_failure',
            'http_status' => $this->httpStatus !== null && $this->httpStatus >= 100 && $this->httpStatus <= 599 ? $this->httpStatus : null,
            'layer' => 'python_generation_adapter',
            ...GenerationValidationDiagnostics::remote($this->validation),
            'attempt_count' => $this->validationAttempts === [] ? $this->attemptCount : array_sum(array_column($this->validationAttempts, 'request_count')),
            'latency_ms' => $this->latencyMs,
            'validation_attempts' => $this->validationAttempts,
            'usage' => $this->validationAttempts === [] ? $this->providerUsage : ValidationAttemptUsage::combine($this->validationAttempts),
        ];
    }
}
