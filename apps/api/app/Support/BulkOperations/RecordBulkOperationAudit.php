<?php

declare(strict_types=1);

namespace App\Support\BulkOperations;

use App\Models\BulkOperation;
use App\Models\BulkOperationAuditEvent;
use App\Models\BulkOperationItem;
use App\Models\BulkOperationItemAttempt;
use Illuminate\Support\Str;

final class RecordBulkOperationAudit
{
    /** @param array<string, bool|int|string|null> $safeContext */
    public function record(
        BulkOperation $operation,
        string $eventType,
        string $executorIdentity,
        array $safeContext,
        ?BulkOperationItem $item = null,
        ?BulkOperationItemAttempt $attempt = null,
    ): BulkOperationAuditEvent {
        return BulkOperationAuditEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $operation->workspace_id,
            'bulk_operation_id' => $operation->id,
            'bulk_operation_item_id' => $item?->id,
            'bulk_operation_item_attempt_id' => $attempt?->id,
            'event_type' => $eventType,
            'initiating_actor_user_id' => $operation->actor_user_id,
            'executor_identity' => $executorIdentity,
            'safe_context' => $safeContext,
            'occurred_at' => now(),
        ]);
    }
}
