<?php

return [
    'ai_url' => env('RETRIEVAL_AI_URL', 'http://ai:8001'),
    'timeout_seconds' => (float) env('RETRIEVAL_TIMEOUT_SECONDS', 10),
    'max_attempts' => (int) env('RETRIEVAL_MAX_ATTEMPTS', 2),
    'candidate_k' => (int) env('RETRIEVAL_CANDIDATE_K', 10),
    'candidate_k_max' => (int) env('RETRIEVAL_CANDIDATE_K_MAX', 100),
    'max_eligible_documents' => (int) env('RETRIEVAL_MAX_ELIGIBLE_DOCUMENTS', 500),
    'planner' => [
        'provider' => env('RETRIEVAL_PLANNER_PROVIDER', 'openai'),
        'model' => env('RETRIEVAL_PLANNER_MODEL', 'gpt-5-mini'),
        'contract_schema_version' => 'plan-response-v2',
        'prompt_version' => 'adr-0022-v1',
        'adapter_version' => 'structured-chat-v2',
    ],
    'embedding' => [
        'estimated_cost_per_million_tokens_usd' => (float) env(
            'EMBEDDING_ESTIMATED_COST_PER_MILLION_TOKENS_USD',
            0.12,
        ),
        'pricing_snapshot' => env(
            'EMBEDDING_PRICING_SNAPSHOT',
            'voyage-pricing-2026-08-12',
        ),
    ],
    'caller' => [
        'key_id' => env('RETRIEVAL_CALLER_HMAC_KEY_ID', 'local-rc1'),
        'secret' => env(
            'RETRIEVAL_CALLER_HMAC_SECRET',
            'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
        ),
    ],
];
