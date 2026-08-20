<?php

declare(strict_types=1);

namespace App\Support\Platform;

final class ObservabilityPolicyManifest
{
    public const VERSION = 'observability-required-targets-v1';

    /** @return array<string, list<string>> */
    public function targets(): array
    {
        return [
            'trace_sampling_percentage' => ['collector'],
            'slow_operation_threshold_seconds' => ['application', 'alerting_rules'],
            'log_retention_days' => ['loki'],
            'trace_retention_days' => ['tempo'],
            'metric_retention_days' => ['prometheus'],
        ];
    }

    public function digest(): string
    {
        return hash('sha256', $this->canonical($this->targets()));
    }

    public function valueDigest(
        string $environment,
        int $version,
        string $setting,
        string $target,
        mixed $value,
        string $planId,
    ): string {
        return hash('sha256', $this->canonical([
            'environment' => $environment,
            'plan_id' => $planId,
            'policy_version' => $version,
            'setting_key' => $setting,
            'target' => $target,
            'value' => $value,
        ]));
    }

    private function canonical(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $item) {
                $value[$key] = is_array($item) ? json_decode($this->canonical($item), true, 512, JSON_THROW_ON_ERROR) : $item;
            }
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
