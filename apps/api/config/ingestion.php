<?php

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
];
