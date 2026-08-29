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
        'extraction_artifact_max_elements' => (int) env('EXTRACTION_ARTIFACT_MAX_ELEMENTS', 100000),
        'extraction_artifact_max_element_text_bytes' => (int) env('EXTRACTION_ARTIFACT_MAX_ELEMENT_TEXT_BYTES', 1048576),
        'extraction_artifact_max_warnings' => (int) env('EXTRACTION_ARTIFACT_MAX_WARNINGS', 10000),
        'extraction_artifact_contract_versions' => ['document-extraction-artifact-v1'],
        'extraction_artifact_upload_seconds' => (int) env('EXTRACTION_ARTIFACT_UPLOAD_SECONDS', 300),
        'extraction_projection_batch_size' => (int) env('EXTRACTION_PROJECTION_BATCH_SIZE', 100),
        'extraction_projection_timeout_seconds' => (int) env('EXTRACTION_PROJECTION_TIMEOUT_SECONDS', 300),
        'extraction_projection_cleanup_grace_seconds' => (int) env('EXTRACTION_PROJECTION_CLEANUP_GRACE_SECONDS', 300),
        'extraction_projection_cleanup_batch_size' => (int) env('EXTRACTION_PROJECTION_CLEANUP_BATCH_SIZE', 25),
        'extraction_cleanup_batch_size' => (int) env('EXTRACTION_CLEANUP_BATCH_SIZE', 50),
        'extraction_cleanup_max_attempts' => (int) env('EXTRACTION_CLEANUP_MAX_ATTEMPTS', 3),
        'materialisation_pipeline' => [
            'worker_contract_version' => 'document-ingestion-requested-v1',
            'artifact_schema_version' => 'document-extraction-artifact-v1',
            'source_extractor_identity' => env('SOURCE_EXTRACTOR_IDENTITY', 'source-extractor-v1'),
            'normaliser_identity' => env('STRUCTURED_NORMALISER_IDENTITY', 'structured-normaliser-v1'),
            'projection_schema_version' => 'structured-projection-v1',
            'digest_algorithm_version' => 'canonical-json-sha256-v1',
            'chunk_strategy_name' => env('CHUNK_STRATEGY_NAME', 'baseline'),
            'chunk_strategy_version' => env('CHUNK_STRATEGY_VERSION', 'v1'),
            'chunk_configuration_fingerprint' => env('CHUNK_CONFIGURATION_FINGERPRINT', hash('sha256', 'baseline-v1')),
        ],
        'content_clone_manifest_disk' => env('CONTENT_CLONE_MANIFEST_DISK', env('DOCUMENT_STORAGE_DISK', 's3')),
        'content_clone_manifest_schema' => 'document-content-clone-manifest-v1',
        'content_clone_manifest_max_bytes' => (int) env('CONTENT_CLONE_MANIFEST_MAX_BYTES', 10485760),
        'content_clone_manifest_max_entries' => (int) env('CONTENT_CLONE_MANIFEST_MAX_ENTRIES', 100000),
        'content_clone_manifest_expiry_seconds' => (int) env('CONTENT_CLONE_MANIFEST_EXPIRY_SECONDS', 3600),
        'content_clone_manifest_cleanup_batch_size' => (int) env('CONTENT_CLONE_MANIFEST_CLEANUP_BATCH_SIZE', 100),
        'content_clone_manifest_cleanup_max_attempts' => (int) env('CONTENT_CLONE_MANIFEST_CLEANUP_MAX_ATTEMPTS', 5),
    ],
];
