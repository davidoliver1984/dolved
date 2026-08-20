<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use Throwable;

final class TraceContextHeaders
{
    /** @return array{traceparent?: string, tracestate?: string} */
    public function current(): array
    {
        $carrier = [];

        try {
            TraceContextPropagator::getInstance()->inject($carrier);
        } catch (Throwable) {
            return [];
        }

        return array_filter(
            $carrier,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );
    }
}
