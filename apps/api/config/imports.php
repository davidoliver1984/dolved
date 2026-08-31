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
    'preflight' => [
        'contract_path' => base_path('../../contracts/events/import-preflight-requested/v1.schema.json'),
        'complete_contract_path' => base_path('../../contracts/http/import-preflight-worker/v1/import-preflight-complete-request-v1.schema.json'),
        'fail_contract_path' => base_path('../../contracts/http/import-preflight-worker/v1/import-preflight-fail-request-v1.schema.json'),
        'lease_seconds' => max(60, (int) env('IMPORT_PREFLIGHT_LEASE_SECONDS', 600)),
        'reclaim_batch_size' => max(1, (int) env('IMPORT_PREFLIGHT_RECLAIM_BATCH_SIZE', 25)),
    ],
    'matching' => [
        'profile_version' => 'family-title-levenshtein-v1',
        'threshold_basis_points' => 6000,
        'maximum_candidates' => 5,
        'maximum_exact_matches' => 5,
        'maximum_normalised_characters' => 200,
    ],
];
