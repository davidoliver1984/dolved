<?php

declare(strict_types=1);

$maxUploadMegabytes = max(1, (int) env('DOCUMENT_MAX_UPLOAD_MB', 25));

return [
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
