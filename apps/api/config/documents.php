<?php

declare(strict_types=1);

$maxUploadMegabytes = max(1, (int) env('DOCUMENT_MAX_UPLOAD_MB', 25));

return [
    'deletion_stuck_after_seconds' => max(60, (int) env('DOCUMENT_DELETION_STUCK_AFTER_SECONDS', 300)),
    'storage_disk' => env('DOCUMENT_STORAGE_DISK', 's3'),
    'upload_disk' => env('DOCUMENT_UPLOAD_DISK', 's3_uploads'),
    'max_upload_mb' => $maxUploadMegabytes,
    'max_upload_bytes' => $maxUploadMegabytes * 1024 * 1024,
    'presigned_url_lifetime_seconds' => max(
        60,
        (int) env('DOCUMENT_PRESIGNED_URL_LIFETIME_SECONDS', 600),
    ),
    'upload_concurrency' => max(
        1,
        (int) env('DOCUMENT_UPLOAD_CONCURRENCY', 3),
    ),
    'administration_queue' => env('DOCUMENT_ADMINISTRATION_QUEUE', 'document-administration'),
    'governance_queue' => env('DOCUMENT_GOVERNANCE_QUEUE', 'document-governance'),
    'governance_digest_cutoff_utc' => env('DOCUMENT_GOVERNANCE_DIGEST_CUTOFF_UTC', '16:00'),
    'governance_email_attempt_lease_seconds' => max(60, (int) env('DOCUMENT_GOVERNANCE_EMAIL_ATTEMPT_LEASE_SECONDS', 120)),
    'governance_email_provider_timeout_seconds' => max(1, (int) env('DOCUMENT_GOVERNANCE_EMAIL_PROVIDER_TIMEOUT_SECONDS', 30)),
    'governance_email_result_margin_seconds' => max(1, (int) env('DOCUMENT_GOVERNANCE_EMAIL_RESULT_MARGIN_SECONDS', 30)),
    'governance_email_retry_ceiling' => max(1, (int) env('DOCUMENT_GOVERNANCE_EMAIL_RETRY_CEILING', 5)),
    'review_due_soon_lead_days' => max(1, (int) env('REVIEW_DUE_SOON_LEAD_DAYS', 14)),
    'deletion_quiescence_retry_seconds' => max(
        1,
        (int) env('DOCUMENT_DELETION_QUIESCENCE_RETRY_SECONDS', 5),
    ),
    'deletion_contract_path' => env(
        'DOCUMENT_DELETION_CONTRACT_PATH',
        base_path('../../contracts/events/document-deletion-requested/v1.schema.json'),
    ),
    'formats' => [
        'pdf' => ['application/pdf'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'doc' => ['application/msword'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'txt' => ['text/plain'],
        'md' => ['text/markdown', 'text/plain'],
    ],
];
