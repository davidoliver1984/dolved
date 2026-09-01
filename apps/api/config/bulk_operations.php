<?php

declare(strict_types=1);

return [
    'max_targets' => (int) env('BULK_OPERATION_MAX_TARGETS', 500),
    'freeze_warning_milliseconds' => (int) env('BULK_OPERATION_FREEZE_WARNING_MILLISECONDS', 1500),
    'attempt_lease_seconds' => (int) env('BULK_OPERATION_ATTEMPT_LEASE_SECONDS', 120),
    'retry_ceiling' => (int) env('BULK_OPERATION_RETRY_CEILING', 3),
];
