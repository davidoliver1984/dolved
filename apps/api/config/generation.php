<?php

declare(strict_types=1);

return [
    'context_policy_version' => env('GENERATION_CONTEXT_POLICY_VERSION', 'whole-evidence-v1'),
    'max_context_characters' => (int) env('GENERATION_MAX_CONTEXT_CHARACTERS', 60000),
    'fingerprint_scheme_version' => 1,
    'provider' => env('GENERATION_PROVIDER', 'openai'),
    'model' => env('GENERATION_MODEL', 'gpt-5-mini'),
    'contract_version' => env('GENERATION_CONTRACT_VERSION', 'generation-result-v1'),
    'prompt_version' => env('GENERATION_PROMPT_VERSION', 'grounded-generation-v2'),
    'adapter_version' => env('GENERATION_ADAPTER_VERSION', 'openai-responses-v2'),
    'delivery_mode' => env('GENERATION_DELIVERY_MODE', 'streaming_parts'),
    'quality_affecting_configuration' => [
        'reasoning_effort' => env('GENERATION_REASONING_EFFORT', 'low'),
        'max_output_tokens' => (int) env('GENERATION_MAX_OUTPUT_TOKENS', 4096),
        'context_window_tokens' => (int) env('GENERATION_CONTEXT_WINDOW_TOKENS', 400000),
        'store' => false,
        'truncation' => 'disabled',
    ],
];
