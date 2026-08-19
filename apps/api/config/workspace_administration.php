<?php

return [
    'invitation_days' => max(1, (int) env('WORKSPACE_INVITATION_DAYS', 7)),
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),
    'sse_reauthorise_seconds' => max(1, (int) env('SSE_REAUTHORISE_SECONDS', 1)),
];
