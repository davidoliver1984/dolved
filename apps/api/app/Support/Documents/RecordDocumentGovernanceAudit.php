<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentGovernanceActorType;
use App\Enums\DocumentGovernanceTargetScope;
use App\Models\Document;
use App\Models\DocumentGovernanceAuditEvent;
use App\Models\User;
use Illuminate\Support\Str;

final class RecordDocumentGovernanceAudit
{
    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    public function record(Document $document, User $actor, string $action, array $before, array $after, ?string $reason = null): void
    {
        DocumentGovernanceAuditEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $document->workspace_id,
            'document_family_id' => $document->document_family_id,
            'document_id' => $document->id,
            'target_scope' => DocumentGovernanceTargetScope::Version,
            'actor_type' => DocumentGovernanceActorType::Human,
            'actor_user_id' => $actor->id,
            'system_actor_code' => null,
            'action' => $action,
            'reason' => $reason,
            'previous_values' => $before,
            'new_values' => $after,
            'occurred_at' => now(),
        ]);
    }
}
