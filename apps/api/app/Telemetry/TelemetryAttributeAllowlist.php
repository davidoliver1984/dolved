<?php

declare(strict_types=1);

namespace App\Telemetry;

final class TelemetryAttributeAllowlist
{
    /**
     * @param  array<string, bool|int|float|string|array|null>  $attributes
     * @return array<string, bool|int|float|string|array|null>
     */
    public function trace(array $attributes): array
    {
        return $this->filter(
            $attributes,
            config('telemetry.trace_attributes', []),
        );
    }

    /**
     * @param  array<string, bool|int|float|string|array|null>  $attributes
     * @return array<string, bool|int|float|string|array|null>
     */
    public function metric(array $attributes): array
    {
        return $this->filter(
            $attributes,
            config('telemetry.metric_attributes', []),
        );
    }

    /**
     * @param  array<string, bool|int|float|string|array|null>  $attributes
     * @param  array<int, string>  $allowed
     * @return array<string, bool|int|float|string|array|null>
     */
    private function filter(array $attributes, array $allowed): array
    {
        return array_intersect_key(
            $attributes,
            array_flip($allowed),
        );
    }
}
