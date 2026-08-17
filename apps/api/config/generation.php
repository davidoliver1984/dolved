<?php

declare(strict_types=1);

return [
    'context_policy_version' => env('GENERATION_CONTEXT_POLICY_VERSION', 'whole-evidence-v1'),
    'max_context_characters' => (int) env('GENERATION_MAX_CONTEXT_CHARACTERS', 60000),
    'fingerprint_scheme_version' => 1,
];
