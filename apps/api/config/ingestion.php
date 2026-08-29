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
    'orchestration' => [
        'lease_seconds' => (int) env('INGESTION_PROCESSING_LEASE_SECONDS', 120),
        'chunk_batch_size' => (int) env('INGESTION_CHUNK_BATCH_SIZE', 50),
        'chunk_body_bytes' => (int) env('INGESTION_CHUNK_BODY_BYTES', 1048576),
        'resume_page_size' => (int) env('INGESTION_RESUME_PAGE_SIZE', 50),
        'extraction_artifact_disk' => env('EXTRACTION_ARTIFACT_DISK', env('DOCUMENT_STORAGE_DISK', 's3')),
        'extraction_artifact_upload_disk' => env('EXTRACTION_ARTIFACT_UPLOAD_DISK', env('DOCUMENT_UPLOAD_DISK', 's3_uploads')),
        'extraction_artifact_max_bytes' => (int) env('EXTRACTION_ARTIFACT_MAX_BYTES', 52428800),
        'extraction_artifact_upload_seconds' => (int) env('EXTRACTION_ARTIFACT_UPLOAD_SECONDS', 300),
        'extraction_projection_batch_size' => (int) env('EXTRACTION_PROJECTION_BATCH_SIZE', 100),
        'extraction_projection_cleanup_grace_seconds' => (int) env('EXTRACTION_PROJECTION_CLEANUP_GRACE_SECONDS', 300),
        'extraction_projection_cleanup_batch_size' => (int) env('EXTRACTION_PROJECTION_CLEANUP_BATCH_SIZE', 25),
        'extraction_cleanup_batch_size' => (int) env('EXTRACTION_CLEANUP_BATCH_SIZE', 50),
        'extraction_cleanup_max_attempts' => (int) env('EXTRACTION_CLEANUP_MAX_ATTEMPTS', 3),
    ],
];
