<?php

$primaryKeyId = (string) env(
    'INGESTION_WORKER_HMAC_PRIMARY_KEY_ID',
    'local-v1',
);
$secondaryKeyId = (string) env(
    'INGESTION_WORKER_HMAC_SECONDARY_KEY_ID',
    '',
);
$workerKeys = array_filter([
    $primaryKeyId => env(
        'INGESTION_WORKER_HMAC_PRIMARY_SECRET',
    ),
    $secondaryKeyId => env('INGESTION_WORKER_HMAC_SECONDARY_SECRET'),
], static fn (mixed $secret, string|int $keyId): bool => (
    is_string($keyId)
    && $keyId !== ''
    && is_string($secret)
    && $secret !== ''
), ARRAY_FILTER_USE_BOTH);

return [
    'contract_path' => env(
        'INGESTION_CONTRACT_PATH',
        base_path('../../contracts/events/document-ingestion-requested/v1.schema.json'),
    ),
    'publisher' => [
        'batch_size' => (int) env('OUTBOX_PUBLISHER_BATCH_SIZE', 10),
        'poll_interval_seconds' => (int) env('OUTBOX_PUBLISHER_POLL_SECONDS', 2),
        'claim_lease_seconds' => (int) env('OUTBOX_CLAIM_LEASE_SECONDS', 60),
        'retry_base_seconds' => (int) env('OUTBOX_RETRY_BASE_SECONDS', 5),
        'retry_max_seconds' => (int) env('OUTBOX_RETRY_MAX_SECONDS', 300),
    ],
    'worker_auth' => [
        'identity' => 'ingestion-worker',
        'keys' => $workerKeys,
        'max_clock_skew_seconds' => (int) env(
            'INGESTION_WORKER_HMAC_MAX_CLOCK_SKEW_SECONDS',
            300,
        ),
    ],
];
