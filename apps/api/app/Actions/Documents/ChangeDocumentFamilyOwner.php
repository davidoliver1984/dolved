<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\DocumentFamilyOwnerChangeException;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceCommand;
use App\Models\User;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final readonly class ChangeDocumentFamilyOwner
{
    public function __construct(private RecordDocumentGovernanceAudit $audit) {}

    /** @return array{family: DocumentFamily, replayed: bool, changed: bool} */
    public function handle(
        DocumentFamily $family,
        User $actor,
        User $intendedOwner,
        int $expectedGeneration,
        int $expectedOwnerId,
        string $idempotencyKey,
    ): array {
        return DB::transaction(function () use ($family, $actor, $intendedOwner, $expectedGeneration, $expectedOwnerId, $idempotencyKey): array {
            $eligible = $intendedOwner->disabled_at === null
                && $intendedOwner->workspaceMemberships()->where('workspace_id', $family->workspace_id)->exists();
            if (! $eligible) {
                throw new DocumentFamilyOwnerChangeException('owner_change_intended_owner_ineligible');
            }

            $payload = [
                'expected_generation' => $expectedGeneration,
                'expected_owner_user_id' => $expectedOwnerId,
                'family_public_id' => $family->public_id,
                'intended_owner_public_id' => $intendedOwner->public_id,
            ];
            ksort($payload);
            $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $inserted = DB::table('document_governance_commands')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $family->workspace_id,
                'purpose' => 'document_family.owner.change',
                'idempotency_key' => $idempotencyKey,
                'actor_user_id' => $actor->id,
                'target_kind' => 'document_family',
                'target_document_id' => null,
                'target_state_at_creation' => null,
                'target_document_family_id' => $family->id,
                'target_document_family_public_id' => $family->public_id,
                'expected_current_owner_user_id' => $expectedOwnerId,
                'expected_current_generation' => $expectedGeneration,
                'intended_new_owner_user_id' => $intendedOwner->id,
                'request_payload_digest' => $digest,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $command = DocumentGovernanceCommand::query()
                ->where('workspace_id', $family->workspace_id)
                ->where('purpose', 'document_family.owner.change')
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $command) {
                throw new DocumentFamilyOwnerChangeException('owner_change_acquisition_inconsistency');
            }
            if (! hash_equals($command->request_payload_digest, $digest)) {
                throw new DocumentFamilyOwnerChangeException('idempotency_key_conflict');
            }
            if ($inserted === 0) {
                if ($command->completed_at === null) {
                    throw new DocumentFamilyOwnerChangeException('owner_change_command_incomplete');
                }

                return ['family' => $family->fresh(), 'replayed' => true, 'changed' => (bool) ($command->result['changed'] ?? false)];
            }

            if (DB::getDriverName() === 'pgsql') {
                try {
                    $row = DB::selectOne('SELECT apply_document_family_owner_change(?) AS result', [$command->id]);
                } catch (QueryException $exception) {
                    foreach ([
                        'owner_change_command_missing',
                        'owner_change_purpose_invalid',
                        'owner_change_command_already_completed',
                        'owner_change_command_incomplete',
                        'owner_change_target_family_missing',
                        'owner_change_workspace_mismatch',
                        'owner_change_precondition_stale',
                    ] as $reason) {
                        if (str_contains($exception->getMessage(), $reason)) {
                            throw new DocumentFamilyOwnerChangeException($reason);
                        }
                    }

                    throw $exception;
                }
                if ($row === null || ! is_string($row->result ?? null)) {
                    throw new DocumentFamilyOwnerChangeException('owner_change_result_invalid');
                }
                try {
                    /** @var array{changed: bool, owner_user_id: int, owner_assignment_generation: int} $result */
                    $result = json_decode($row->result, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new DocumentFamilyOwnerChangeException('owner_change_result_invalid');
                }

                return [
                    'family' => DocumentFamily::query()->findOrFail($family->id),
                    'replayed' => false,
                    'changed' => $result['changed'],
                ];
            }

            $locked = DocumentFamily::query()->lockForUpdate()->find($family->id);
            if (! $locked || $locked->workspace_id !== $command->workspace_id) {
                throw new DocumentFamilyOwnerChangeException($locked ? 'owner_change_workspace_mismatch' : 'owner_change_target_family_missing');
            }
            if ($locked->owner_user_id === $intendedOwner->id) {
                $result = ['changed' => false, 'owner_user_id' => $locked->owner_user_id, 'owner_assignment_generation' => $locked->owner_assignment_generation];
            } else {
                if ($locked->owner_user_id !== $expectedOwnerId || $locked->owner_assignment_generation !== $expectedGeneration) {
                    throw new DocumentFamilyOwnerChangeException('owner_change_precondition_stale');
                }
                $before = ['owner_user_id' => $locked->owner_user_id, 'owner_assignment_generation' => $locked->owner_assignment_generation];
                $locked->forceFill([
                    'owner_user_id' => $intendedOwner->id,
                    'owner_assignment_generation' => $locked->owner_assignment_generation + 1,
                ])->save();
                $after = ['owner_user_id' => $locked->owner_user_id, 'owner_assignment_generation' => $locked->owner_assignment_generation];
                $this->audit->recordFamily($locked, $actor, 'document_family_owner_changed', $before, $after);
                $result = ['changed' => true, ...$after];
            }
            $command->forceFill(['status' => 'completed', 'result' => $result, 'completed_at' => now()])->save();

            return ['family' => $locked->refresh(), 'replayed' => false, 'changed' => $result['changed']];
        }, 3);
    }
}
