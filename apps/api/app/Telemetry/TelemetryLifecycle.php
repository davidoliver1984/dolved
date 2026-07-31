<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Throwable;

final class TelemetryLifecycle
{
    public function __construct(
        private readonly TracerProviderInterface $tracerProvider,
        private readonly MeterProviderInterface $meterProvider,
    ) {}

    public function flush(): void
    {
        try {
            $this->tracerProvider->forceFlush();
        } catch (Throwable) {
            // Telemetry must never fail the operation being observed.
        }

        try {
            $this->meterProvider->forceFlush();
        } catch (Throwable) {
            // Telemetry must never fail the operation being observed.
        }
    }

    public function shutdown(): void
    {
        try {
            $this->tracerProvider->shutdown();
        } catch (Throwable) {
            // Telemetry shutdown is best effort.
        }

        try {
            $this->meterProvider->shutdown();
        } catch (Throwable) {
            // Telemetry shutdown is best effort.
        }
    }
}
