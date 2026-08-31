<?php

declare(strict_types=1);

namespace App\Support\Imports;

use App\Exceptions\LegacyUploadCutoverException;
use App\Models\Document;
use App\Models\LegacyUploadCutoverAudit as AuditRecord;
use App\Models\LegacyUploadInitializationGate;
use App\Models\User;
use Illuminate\Support\Str;

final class LegacyUploadCutoverAudit
{
    public function recordHuman(Document $document, LegacyUploadInitializationGate $gate, User $actor): void
    {
        $this->record($document, $gate, 'transition_window_creation', 'human', $actor->id, null);
    }

    public function recordSystem(Document $document, LegacyUploadInitializationGate $gate, string $reason): void
    {
        $this->record($document, $gate, $reason, 'system', null, 'legacy_upload_cutover');
    }

    private function record(
        Document $document,
        LegacyUploadInitializationGate $gate,
        string $reason,
        string $actorType,
        ?int $actorUserId,
        ?string $systemActorCode,
    ): void {
        $existing = AuditRecord::query()->where('document_id', $document->id)->first();
        if ($existing !== null) {
            if ($existing->cutover_operation_id !== $gate->cutover_operation_id) {
                throw LegacyUploadCutoverException::markerConflict();
            }

            return;
        }
        AuditRecord::query()->create([
            'public_id' => (string) Str::uuid(),
            'cutover_operation_id' => $gate->cutover_operation_id,
            'document_id' => $document->id,
            'workspace_id' => $document->workspace_id,
            'actor_type' => $actorType,
            'actor_user_id' => $actorUserId,
            'system_actor_code' => $systemActorCode,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
