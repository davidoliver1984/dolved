<?php

$embeddingAttempts = max(1, (int) env('EMBEDDING_MAX_ATTEMPTS', 4));
$embeddingRequestTimeout = (float) env('EMBEDDING_TIMEOUT_SECONDS', 10);
$embeddingInitialBackoff = (float) env('EMBEDDING_INITIAL_BACKOFF_SECONDS', 15);
$embeddingMaximumBackoff = (float) env('EMBEDDING_MAX_BACKOFF_SECONDS', 120);
$providerCooldownMaximum = (float) env('EMBEDDING_MAX_PROVIDER_COOLDOWN_SECONDS', 120);
$qdrantRequestTimeout = (float) env('QDRANT_TIMEOUT_SECONDS', 10);
$rerankerAttempts = max(1, (int) env('RERANKER_MAX_ATTEMPTS', 3));
$rerankerRequestTimeout = (float) env('RERANKER_TIMEOUT_SECONDS', 10);
$rerankerInitialBackoff = (float) env('RERANKER_INITIAL_BACKOFF_SECONDS', 15);
$rerankerMaximumBackoff = (float) env('RERANKER_MAX_BACKOFF_SECONDS', 90);
$rerankerCooldownMaximum = (float) env('RERANKER_MAX_PROVIDER_COOLDOWN_SECONDS', 90);
$localExecutionAllowance = (float) env('RETRIEVAL_LOCAL_EXECUTION_ALLOWANCE_SECONDS', 20);
$timeoutSafetyMargin = (float) env('RETRIEVAL_TIMEOUT_SAFETY_MARGIN_SECONDS', 20);
$searchMinimumTimeout = ($embeddingAttempts * $embeddingRequestTimeout)
    + (($embeddingAttempts - 1) * $providerCooldownMaximum)
    + (4 * $qdrantRequestTimeout)
    + $localExecutionAllowance
    + $timeoutSafetyMargin;
$rerankerMaximumSides = 2;
$rerankerMinimumTimeout = $rerankerMaximumSides * (
    ($rerankerAttempts * $rerankerRequestTimeout)
    + (($rerankerAttempts - 1) * $rerankerCooldownMaximum)
) + $localExecutionAllowance + $timeoutSafetyMargin;
$minimumTimeout = max($searchMinimumTimeout, $rerankerMinimumTimeout);

return [
    'ai_url' => env('RETRIEVAL_AI_URL', 'http://ai:8001'),
    'timeout_seconds' => (float) env('RETRIEVAL_TIMEOUT_SECONDS', 480),
    'minimum_timeout_seconds' => $minimumTimeout,
    'timeout_budget' => [
        'embedding_attempts' => $embeddingAttempts,
        'embedding_request_timeout_seconds' => $embeddingRequestTimeout,
        'embedding_initial_backoff_seconds' => $embeddingInitialBackoff,
        'embedding_maximum_backoff_seconds' => $embeddingMaximumBackoff,
        'provider_cooldown_maximum_seconds' => $providerCooldownMaximum,
        'qdrant_request_timeout_seconds' => $qdrantRequestTimeout,
        'maximum_qdrant_requests' => 4,
        'local_execution_allowance_seconds' => $localExecutionAllowance,
        'safety_margin_seconds' => $timeoutSafetyMargin,
        'search_minimum_timeout_seconds' => $searchMinimumTimeout,
        'reranker_attempts_per_side' => $rerankerAttempts,
        'reranker_request_timeout_seconds' => $rerankerRequestTimeout,
        'reranker_initial_backoff_seconds' => $rerankerInitialBackoff,
        'reranker_maximum_backoff_seconds' => $rerankerMaximumBackoff,
        'reranker_provider_cooldown_maximum_seconds' => $rerankerCooldownMaximum,
        'reranker_maximum_sides' => $rerankerMaximumSides,
        'reranker_minimum_timeout_seconds' => $rerankerMinimumTimeout,
    ],
    'max_attempts' => (int) env('RETRIEVAL_MAX_ATTEMPTS', 2),
    'candidate_k' => (int) env('RETRIEVAL_CANDIDATE_K', 10),
    'candidate_k_max' => (int) env('RETRIEVAL_CANDIDATE_K_MAX', 100),
    'max_eligible_documents' => (int) env('RETRIEVAL_MAX_ELIGIBLE_DOCUMENTS', 500),
    'planner' => [
        'provider' => env('RETRIEVAL_PLANNER_PROVIDER', 'openai'),
        'model' => env('RETRIEVAL_PLANNER_MODEL', 'gpt-5-mini'),
        'contract_schema_version' => 'plan-response-v2',
        'prompt_version' => 'adr-0022-v5',
        'adapter_version' => 'structured-chat-v3',
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
