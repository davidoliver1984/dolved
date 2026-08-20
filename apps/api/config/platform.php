<?php

declare(strict_types=1);

return [
    'administration_credentials' => array_values(array_filter([
        [
            'key_id' => env('PLATFORM_ADMIN_PRIMARY_KEY_ID'),
            'version' => env('PLATFORM_ADMIN_PRIMARY_KEY_VERSION', 'v1'),
            'secret' => env('PLATFORM_ADMIN_PRIMARY_SECRET'),
            'revoked' => filter_var(env('PLATFORM_ADMIN_PRIMARY_REVOKED', false), FILTER_VALIDATE_BOOL),
        ],
        [
            'key_id' => env('PLATFORM_ADMIN_SECONDARY_KEY_ID'),
            'version' => env('PLATFORM_ADMIN_SECONDARY_KEY_VERSION', 'v1'),
            'secret' => env('PLATFORM_ADMIN_SECONDARY_SECRET'),
            'revoked' => filter_var(env('PLATFORM_ADMIN_SECONDARY_REVOKED', false), FILTER_VALIDATE_BOOL),
        ],
    ], fn (array $credential): bool => is_string($credential['key_id']) && $credential['key_id'] !== '')),
    'metrics' => [
        'base_url' => env('OPERATIONAL_METRICS_URL', 'http://otel-lgtm:9090'),
        'timeout_seconds' => max(0.1, (float) env('OPERATIONAL_METRICS_TIMEOUT_SECONDS', 1.0)),
        'cache_seconds' => max(1, (int) env('OPERATIONAL_METRICS_CACHE_SECONDS', 10)),
        'grafana_url' => env('OPERATIONAL_GRAFANA_URL', 'http://127.0.0.1:3001'),
    ],
];
