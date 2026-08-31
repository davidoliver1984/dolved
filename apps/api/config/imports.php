<?php

declare(strict_types=1);

return [
    'staging_disk' => env('IMPORT_STAGING_DISK', 's3_uploads'),
    'storage_disk' => env('IMPORT_STORAGE_DISK', 's3'),
    'staging_prefix' => 'imports/workspaces',
    'retention_days' => max(1, (int) env('IMPORT_RETENTION_DAYS', 7)),
    'presigned_url_lifetime_seconds' => max(
        60,
        (int) env('IMPORT_PRESIGNED_URL_LIFETIME_SECONDS', 600),
    ),
];
