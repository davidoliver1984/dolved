<?php

return [
    'context_policy_version' => 'bounded-completed-turns-v1',
    'context_turn_limit' => (int) env('CONVERSATION_CONTEXT_TURN_LIMIT', 3),
    'context_max_characters' => (int) env('CONVERSATION_CONTEXT_MAX_CHARACTERS', 24_000),
    'title_max_characters' => (int) env('CONVERSATION_TITLE_MAX_CHARACTERS', 80),
    'message_max_characters' => (int) env('CONVERSATION_MESSAGE_MAX_CHARACTERS', 8_000),
    'clarification_max_characters' => (int) env('CONVERSATION_CLARIFICATION_MAX_CHARACTERS', 1_000),
    'controlled_renderer_version' => 'controlled-conversation-response-v1',
    'candidate_k' => (int) env('CONVERSATION_RETRIEVAL_CANDIDATE_K', 40),
    'run_timeout_seconds' => (int) env('CONVERSATION_RUN_TIMEOUT_SECONDS', 900),
    'queue' => env('CONVERSATION_QUEUE', 'conversation-generation'),
    'contextualiser' => [
        'provider' => env('CONTEXTUALISER_PROVIDER', 'openai'),
        'model' => env('CONTEXTUALISER_MODEL', 'gpt-5-mini'),
        'contract_version' => 'conversation-contextualisation-v1',
        'prompt_version' => 'conversation-contextualisation-v1',
        'adapter_version' => 'structured-chat-v1',
    ],
];
