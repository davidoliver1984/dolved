<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use Throwable;

final class OperationalTelemetry
{
    private readonly MeterInterface $meter;

    private readonly CounterInterface $operationCount;

    private readonly HistogramInterface $operationDuration;

    private readonly UpDownCounterInterface $dependencyAvailable;

    private readonly HistogramInterface $dependencyDuration;

    private readonly UpDownCounterInterface $queueDepth;

    private readonly UpDownCounterInterface $queueOldestAge;

    private readonly UpDownCounterInterface $stuckOperations;

    public function __construct(MeterProviderInterface $meters, private readonly TelemetryAttributeAllowlist $allowlist)
    {
        // Keep the meter alive alongside its instruments. The OTLP reader
        // unregisters instrument sources when their owning meter is released.
        // Give this scope a stable version. The pilot OTLP pipeline suppresses
        // the unversioned scope after its first short-lived CLI process exits.
        $this->meter = $meters->getMeter('dolved.laravel.operations', '1.0.0');
        $this->operationCount = $this->meter->createCounter('rag.operation.count', '{operation}', 'Count of bounded platform operations.');
        $this->operationDuration = $this->meter->createHistogram('rag.operation.duration', 's', 'Duration of bounded platform operations.');
        // The OTLP/Prometheus backend used by the pilot currently drops
        // synchronous Gauge data points. Snapshot commands run in a fresh
        // process, so a non-monotonic sum safely exports each absolute value.
        $this->dependencyAvailable = $this->meter->createUpDownCounter('rag.snapshot.dependency.available', '1', 'Whether a dependency probe succeeded.');
        $this->dependencyDuration = $this->meter->createHistogram('rag.dependency.duration', 's', 'Dependency probe duration.');
        $this->queueDepth = $this->meter->createUpDownCounter('rag.snapshot.queue.depth', '{message}', 'Current queue or durable outbox depth.');
        $this->queueOldestAge = $this->meter->createUpDownCounter('rag.snapshot.queue.oldest_message_age', 's', 'Age of the oldest pending queue or outbox item.');
        $this->stuckOperations = $this->meter->createUpDownCounter('rag.snapshot.stuck_operation.count', '{operation}', 'Current count of bounded stale operations.');
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
            $this->dependencyAvailable->add($available ? 1 : 0, $attributes);
            $this->dependencyDuration->record(max(0.0, $durationSeconds), $attributes);
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function queue(string $destination, int $depth, float $oldestAgeSeconds): void
    {
        try {
            $attributes = $this->allowlist->metric(['messaging.destination.name' => $destination]);
            $this->queueDepth->add(max(0, $depth), $attributes);
            $this->queueOldestAge->add(max(0.0, $oldestAgeSeconds), $attributes);
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function stuck(string $stage, int $count): void
    {
        try {
            $this->stuckOperations->add(max(0, $count), $this->allowlist->metric(['rag.operation.stage' => $stage]));
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }
}
