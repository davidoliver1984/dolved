<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Contracts\Documents\ExportSourceHold;
use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentFamilyDeletionStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Enums\IngestionAttemptStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Jobs\AdvanceDocumentDeletion;
use App\Models\Document;
use App\Models\DocumentDeletionOperation;
use App\Models\DocumentFamily;
use App\Models\DocumentFamilyDeletionOperation;
use App\Models\IngestionAuditEvent;
use App\Models\User;
use App\Support\Documents\FamilyDeletionConfirmationDigest;
use App\Support\Documents\FamilyDeletionState;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ConfirmDocumentFamilyDeletion
{
    public function __construct(
        private FamilyDeletionState $states,
        private FamilyDeletionConfirmationDigest $digests,
        private ExportSourceHold $exportHolds,
        private RecordDocumentGovernanceAudit $audit,
        private ReconcileDocumentFamilyDeletion $reconcile,
    ) {}

    public function handle(DocumentFamily $family, User $actor, string $confirmationDigest, string $idempotencyKey): DocumentFamilyDeletionOperation
    {
        $binding = $this->digests->verify($confirmationDigest);
        if ($binding['actor_id'] !== $actor->id || $binding['family_id'] !== $family->id) {
            throw new DocumentGovernanceException('The family-deletion confirmation is bound to another actor or family.');
        }

        [$operation, $childIds] = DB::transaction(function () use ($family, $actor, $binding, $idempotencyKey): array {
            $locked = DocumentFamily::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $existing = DocumentFamilyDeletionOperation::query()
                ->where('document_family_id', $locked->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                if ($existing->confirmation_state_digest !== $binding['state_digest']) {
                    throw new DocumentGovernanceException('The idempotency key was already used for a different family-deletion state.');
                }

                return [$existing, []];
            }
            if ($locked->tombstoned_at !== null || DocumentFamilyDeletionOperation::query()
                ->where('document_family_id', $locked->id)
                ->whereIn('status', [DocumentFamilyDeletionStatus::Pending->value, DocumentFamilyDeletionStatus::Processing->value, DocumentFamilyDeletionStatus::PartiallyFailed->value])
                ->exists()) {
                throw new DocumentGovernanceException('A family deletion is already active or complete.');
            }
            /** @var Collection<int, Document> $versions */
            $versions = Document::query()->where('document_family_id', $locked->id)->orderBy('id')->lockForUpdate()->get();
            $state = $this->states->capture($locked, $versions);
            $stateDigest = $this->states->digest($state);
            if (! hash_equals($binding['state_digest'], $stateDigest)) {
                throw new DocumentGovernanceException('The family changed after preview; a fresh preview is required.');
            }
            if ($state['blockers']['open_clone_operations'] !== [] || $state['blockers']['open_deletion_operations'] !== []) {
                throw new DocumentGovernanceException('An active content clone or deletion blocks family deletion.');
            }
            foreach ($versions as $version) {
                if ($this->exportHolds->blocksPhysicalRemoval($version)) {
                    throw new DocumentGovernanceException('An active export-source hold blocks family deletion.');
                }
            }

            $operation = DocumentFamilyDeletionOperation::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'document_family_id' => $locked->id,
                'requested_by_user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'status' => DocumentFamilyDeletionStatus::Pending,
                'confirmation_state_digest' => $stateDigest,
                'version_snapshot' => $state['versions'],
                'child_count' => $versions->count(),
            ]);

            $childIds = [];
            foreach ($versions as $version) {
                $snapshot = collect($state['versions'])->firstWhere('id', $version->id);
                if (in_array($snapshot['classification'], ['current', 'scheduled'], true)) {
                    $before = $version->only(['governance_status', 'effective_from', 'approved_at', 'withdrawn_at']);
                    $version->governance_status = DocumentGovernanceStatus::Withdrawn;
                    $version->withdrawn_at = now();
                    $version->save();
                    $this->audit->record(
                        $version,
                        $actor,
                        $snapshot['classification'] === 'scheduled' ? 'cancelled_for_family_deletion' : 'withdrawn_for_family_deletion',
                        $before,
                        $version->only(array_keys($before)),
                    );
                }

                $existingChild = DocumentDeletionOperation::query()->where('document_id', $version->id)->first();
                if ($existingChild !== null) {
                    if ($existingChild->status !== DocumentDeletionStatus::Completed) {
                        throw new DocumentGovernanceException('A non-completed version deletion blocks family deletion.');
                    }
                    $existingChild->forceFill(['document_family_deletion_operation_id' => $operation->id])->save();
                    $childIds[] = $existingChild->id;

                    continue;
                }
                $activeAttemptIds = $version->ingestionAttempts()->whereIn('status', [
                    IngestionAttemptStatus::Open->value,
                    IngestionAttemptStatus::Sealed->value,
                    IngestionAttemptStatus::PublicationAuthorised->value,
                ])->orderBy('id')->pluck('id')->all();
                $child = DocumentDeletionOperation::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'workspace_id' => $version->workspace_id,
                    'document_id' => $version->id,
                    'document_family_deletion_operation_id' => $operation->id,
                    'requested_by_user_id' => $actor->id,
                    'correlation_id' => (string) Str::uuid(),
                    'status' => DocumentDeletionStatus::AwaitingQuiescence,
                    'active_attempt_ids' => $activeAttemptIds,
                ]);
                $version->forceFill(['status' => DocumentStatus::Deleting])->save();
                IngestionAuditEvent::query()->create([
                    'event_id' => $child->public_id,
                    'workspace_id' => $version->workspace_id,
                    'document_id' => $version->id,
                    'action' => 'family_deletion_requested',
                    'outcome' => 'awaiting_quiescence',
                    'context' => ['family_deletion_public_id' => $operation->public_id, 'actor_user_id' => $actor->id],
                    'occurred_at' => now(),
                ]);
                $childIds[] = $child->id;
            }
            $operation->forceFill(['status' => DocumentFamilyDeletionStatus::Processing])->save();
            $this->audit->recordFamily($locked, $actor, 'family_deletion_confirmed', [], [
                'operation_public_id' => $operation->public_id,
                'child_count' => $operation->child_count,
                'confirmation_state_digest' => $stateDigest,
            ]);

            return [$operation, $childIds];
        }, attempts: 3);

        foreach ($childIds as $childId) {
            AdvanceDocumentDeletion::dispatch($childId);
        }
        $this->reconcile->handle($operation->id);

        return $operation->refresh();
    }
}
