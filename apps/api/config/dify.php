<?php

return [
    'base_url' => rtrim((string) env('DIFY_BASE_URL', 'http://dify-nginx'), '/'),
    'app_api_key' => env('DIFY_APP_API_KEY'),
    'knowledge_api_key' => env('DIFY_KNOWLEDGE_API_KEY'),
    'timeout_seconds' => (int) env('DIFY_TIMEOUT_SECONDS', 120),
    'connect_timeout_seconds' => (int) env('DIFY_CONNECT_TIMEOUT_SECONDS', 5),
    'prompt_version' => env('DIFY_PROMPT_VERSION', 'dolved-source-fidelity-v1'),
    'contract_version' => 'dify-chatflow-answer-v1',
    'adapter_version' => 'dify-service-api-v1',
    'document_sync_queue' => env('DIFY_DOCUMENT_SYNC_QUEUE', 'document-administration'),
    'usage_observation_max_age_seconds' => max(300, (int) env('DIFY_USAGE_OBSERVATION_MAX_AGE_SECONDS', 7200)),
    'document_chunk_size' => 1000,
    'document_chunk_overlap' => 100,
    'document_separator' => "\n\n",
];
