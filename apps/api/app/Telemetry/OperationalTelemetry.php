<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Throwable;

final class OperationalTelemetry
{
    private readonly CounterInterface $operationCount;

    private readonly HistogramInterface $operationDuration;

    private readonly GaugeInterface $dependencyAvailable;

    private readonly HistogramInterface $dependencyDuration;

    private readonly GaugeInterface $queueDepth;

    private readonly GaugeInterface $queueOldestAge;

    private readonly GaugeInterface $stuckOperations;

    public function __construct(MeterProviderInterface $meters, private readonly TelemetryAttributeAllowlist $allowlist)
    {
        $meter = $meters->getMeter('dolved.laravel.operations');
        $this->operationCount = $meter->createCounter('rag.operation.count', '{operation}', 'Count of bounded platform operations.');
        $this->operationDuration = $meter->createHistogram('rag.operation.duration', 's', 'Duration of bounded platform operations.');
        $this->dependencyAvailable = $meter->createGauge('rag.dependency.available', '1', 'Whether a dependency probe succeeded.');
        $this->dependencyDuration = $meter->createHistogram('rag.dependency.duration', 's', 'Dependency probe duration.');
        $this->queueDepth = $meter->createGauge('rag.queue.depth', '{message}', 'Current queue or durable outbox depth.');
        $this->queueOldestAge = $meter->createGauge('rag.queue.oldest_message_age', 's', 'Age of the oldest pending queue or outbox item.');
        $this->stuckOperations = $meter->createGauge('rag.stuck_operation.count', '{operation}', 'Current count of bounded stale operations.');
    }

    public function operation(string $stage, string $outcome, float $durationSeconds, ?string $failureCategory = null): void
    {
        try {
            $attributes = $this->allowlist->metric([
                'rag.operation.stage' => $stage,
                'rag.operation.outcome' => $outcome,
                'rag.failure.category' => $failureCategory,
            ]);
            $this->operationCount->add(1, $attributes);
            $this->operationDuration->record(max(0.0, $durationSeconds), $attributes);
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function dependency(string $kind, bool $available, float $durationSeconds): void
    {
        try {
            $attributes = $this->allowlist->metric(['rag.dependency.kind' => $kind]);
            $this->dependencyAvailable->record($available ? 1 : 0, $attributes);
            $this->dependencyDuration->record(max(0.0, $durationSeconds), $attributes);
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function queue(string $destination, int $depth, float $oldestAgeSeconds): void
    {
        try {
            $attributes = $this->allowlist->metric(['messaging.destination.name' => $destination]);
            $this->queueDepth->record(max(0, $depth), $attributes);
            $this->queueOldestAge->record(max(0.0, $oldestAgeSeconds), $attributes);
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function stuck(string $stage, int $count): void
    {
        try {
            $this->stuckOperations->record(max(0, $count), $this->allowlist->metric(['rag.operation.stage' => $stage]));
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }
}
