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
    'reconciliation' => [
        'environment' => env('OBSERVABILITY_RECONCILIATION_ENVIRONMENT', env('APP_ENV', 'local')),
        'max_clock_skew_seconds' => max(30, (int) env('OBSERVABILITY_RECONCILIATION_MAX_CLOCK_SKEW_SECONDS', 300)),
        'nonce_ttl_seconds' => max(300, (int) env('OBSERVABILITY_RECONCILIATION_NONCE_TTL_SECONDS', 900)),
        'max_body_bytes' => 16384,
        'credentials' => array_values(array_filter([
            [
                'key_id' => env('OBSERVABILITY_RECONCILIATION_PRIMARY_KEY_ID'),
                'version' => env('OBSERVABILITY_RECONCILIATION_PRIMARY_KEY_VERSION', 'v1'),
                'secret' => env('OBSERVABILITY_RECONCILIATION_PRIMARY_SECRET'),
                'revoked' => filter_var(env('OBSERVABILITY_RECONCILIATION_PRIMARY_REVOKED', false), FILTER_VALIDATE_BOOL),
            ],
            [
                'key_id' => env('OBSERVABILITY_RECONCILIATION_SECONDARY_KEY_ID'),
                'version' => env('OBSERVABILITY_RECONCILIATION_SECONDARY_KEY_VERSION', 'v1'),
                'secret' => env('OBSERVABILITY_RECONCILIATION_SECONDARY_SECRET'),
                'revoked' => filter_var(env('OBSERVABILITY_RECONCILIATION_SECONDARY_REVOKED', false), FILTER_VALIDATE_BOOL),
            ],
        ], fn (array $credential): bool => is_string($credential['key_id']) && $credential['key_id'] !== '')),
    ],
];
