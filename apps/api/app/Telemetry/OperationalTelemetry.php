<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use Throwable;

final class OperationalTelemetry
{
    public function __construct(
        private readonly MeterProviderInterface $meters,
        private readonly TelemetryAttributeAllowlist $allowlist,
    ) {}

    public function operation(string $stage, string $outcome, float $durationSeconds, ?string $failureCategory = null): void
    {
        try {
            $meter = $this->meter();
            $attributes = $this->allowlist->metric([
                'rag.operation.stage' => $stage,
                'rag.operation.outcome' => $outcome,
                'rag.failure.category' => $failureCategory,
            ]);
            $meter->createCounter('rag.operation.count', '{operation}', 'Count of bounded platform operations.')->add(1, $attributes);
            $meter->createHistogram('rag.operation.duration', 's', 'Duration of bounded platform operations.')->record(max(0.0, $durationSeconds), $attributes);
            $this->meters->forceFlush();
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function dependency(string $kind, bool $available, float $durationSeconds): void
    {
        try {
            $meter = $this->meter();
            $attributes = $this->allowlist->metric(['rag.dependency.kind' => $kind]);
            // The pilot's OTLP/Prometheus path drops synchronous Gauge points,
            // so each short-lived process exports its absolute snapshot as a
            // non-monotonic sum under a dedicated snapshot series name.
            $meter->createUpDownCounter('rag.snapshot.dependency.available', '1', 'Whether a dependency probe succeeded.')->add($available ? 1 : 0, $attributes);
            $meter->createHistogram('rag.dependency.duration', 's', 'Dependency probe duration.')->record(max(0.0, $durationSeconds), $attributes);
            $this->meters->forceFlush();
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function queue(string $destination, int $depth, float $oldestAgeSeconds): void
    {
        try {
            $meter = $this->meter();
            $attributes = $this->allowlist->metric(['messaging.destination.name' => $destination]);
            $meter->createUpDownCounter('rag.snapshot.queue.depth', '{message}', 'Current queue or durable outbox depth.')->add(max(0, $depth), $attributes);
            $meter->createUpDownCounter('rag.snapshot.queue.oldest_message_age', 's', 'Age of the oldest pending queue or outbox item.')->add(max(0.0, $oldestAgeSeconds), $attributes);
            $this->meters->forceFlush();
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    public function stuck(string $stage, int $count): void
    {
        try {
            $meter = $this->meter();
            $meter->createUpDownCounter('rag.snapshot.stuck_operation.count', '{operation}', 'Current count of bounded stale operations.')
                ->add(max(0, $count), $this->allowlist->metric(['rag.operation.stage' => $stage]));
            $this->meters->forceFlush();
        } catch (Throwable) {
            // Operational telemetry never changes application correctness.
        }
    }

    private function meter(): MeterInterface
    {
        // Keep the meter and its instruments alive until the explicit flush.
        // The PHP OTLP reader unregisters short-lived instrument sources once
        // their owning meter is released.
        return $this->meters->getMeter('dolved.laravel.operations', '2.0.0');
    }
}
