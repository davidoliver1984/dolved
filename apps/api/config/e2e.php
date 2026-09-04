<?php

return [
    'resource_marker' => env('E2E_RESOURCE_MARKER'),
    'database_marker' => env('E2E_DATABASE_MARKER'),
    'password' => env('E2E_ACCOUNT_PASSWORD'),
    'deterministic_retrieval_profile_path' => env('E2E_DETERMINISTIC_RETRIEVAL_PROFILE_PATH'),
    'frozen_corpus_root' => env('E2E_FROZEN_CORPUS_ROOT', '/r28-corpus'),
];
