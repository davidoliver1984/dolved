<?php

declare(strict_types=1);

namespace App\Telemetry;

use Illuminate\Database\Events\QueryExecuted;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Throwable;

final class DatabaseTelemetry
{
    private readonly TracerInterface $tracer;

    private readonly HistogramInterface $duration;

    public function __construct(
        TracerProviderInterface $tracerProvider,
        MeterProviderInterface $meterProvider,
        private readonly TelemetryAttributeAllowlist $allowlist,
    ) {
        $this->tracer = $tracerProvider->getTracer('dolved.laravel.database');
        $this->duration = $meterProvider
            ->getMeter('dolved.laravel.database')
            ->createHistogram(
                'db.client.operation.duration',
                's',
                'Duration of Laravel database operations.',
            );
    }

    public function record(QueryExecuted $query): void
    {
        try {
            $durationSeconds = max(0.0, $query->time / 1_000);
            $attributes = [
                'db.system.name' => $query->connection->getDriverName(),
                'db.operation.name' => $this->operation($query->sql),
            ];
            $end = Clock::getDefault()->now();
            $start = max(
                0,
                $end - (int) round($durationSeconds * 1_000_000_000),
            );
            $span = $this->tracer
                ->spanBuilder(
                    'db.'.$attributes['db.operation.name'],
                )
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->setStartTimestamp($start)
                ->setAttributes($this->allowlist->trace($attributes))
                ->startSpan();

            $span->end($end);
            $this->duration->record(
                $durationSeconds,
                $this->allowlist->metric($attributes),
            );
        } catch (Throwable) {
            // Query results must never depend on instrumentation.
        }
    }

    private function operation(string $sql): string
    {
        $operation = strtoupper(
            strtok(ltrim($sql), " \t\n\r") ?: 'OTHER',
        );

        return in_array($operation, [
            'DELETE',
            'INSERT',
            'SELECT',
            'UPDATE',
        ], true)
            ? $operation
            : 'OTHER';
    }
}
