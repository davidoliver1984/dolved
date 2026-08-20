<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Throwable;

final class OperationTracer
{
    private readonly TracerInterface $tracer;

    public function __construct(
        TracerProviderInterface $provider,
        private readonly TelemetryAttributeAllowlist $allowlist,
    ) {
        $this->tracer = $provider->getTracer('dolved.laravel.operations');
    }

    /** @param array<string, mixed> $attributes */
    public function run(string $name, array $attributes, callable $operation): mixed
    {
        $span = $this->tracer->spanBuilder($name)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes($this->allowlist->trace($attributes))
            ->startSpan();
        $scope = $span->activate();
        try {
            $result = $operation();
            $span->setAttribute('rag.operation.outcome', 'completed');

            return $result;
        } catch (Throwable $exception) {
            $span->setAttributes($this->allowlist->trace([
                'rag.operation.outcome' => 'failed',
                'error.type' => $exception::class,
            ]));
            $span->setStatus(StatusCode::STATUS_ERROR);
            throw $exception;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
