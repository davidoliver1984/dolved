<?php

declare(strict_types=1);

return [
    'enabled' => ! filter_var(
        env('OTEL_SDK_DISABLED', false),
        FILTER_VALIDATE_BOOL,
    ),
    'service_name' => env('OTEL_SERVICE_NAME', 'rag-platform-api'),
    'exporter' => [
        'endpoint' => env(
            'OTEL_EXPORTER_OTLP_ENDPOINT',
            'http://otel-collector:4318',
        ),
        'protocol' => env(
            'OTEL_EXPORTER_OTLP_PROTOCOL',
            'http/protobuf',
        ),
        'timeout_milliseconds' => max(
            1,
            (int) env('OTEL_EXPORTER_OTLP_TIMEOUT', 250),
        ),
        'metrics_temporality' => env(
            'OTEL_EXPORTER_OTLP_METRICS_TEMPORALITY_PREFERENCE',
            'cumulative',
        ),
    ],
    'trace_attributes' => [
        'db.operation.name',
        'db.system.name',
        'error.type',
        'http.request.method',
        'http.response.status_code',
        'http.route',
        'messaging.destination.name',
        'messaging.operation.type',
        'messaging.system',
        'rag.correlation.id',
        'rag.document.id',
        'rag.event.id',
        'rag.outbox.outcome',
        'rag.workspace.id',
    ],
    'metric_attributes' => [
        'db.operation.name',
        'db.system.name',
        'http.request.method',
        'http.response.status_code',
        'http.route',
        'messaging.destination.name',
        'messaging.operation.type',
        'messaging.system',
        'rag.outbox.outcome',
    ],
];
