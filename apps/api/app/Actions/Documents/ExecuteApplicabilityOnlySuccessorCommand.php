<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Exceptions\DocumentGovernanceIdempotencyConflict;
use App\Models\Document;
use App\Models\DocumentGovernanceCommand;
use App\Models\OrganisationalLocation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ExecuteApplicabilityOnlySuccessorCommand
{
    public function __construct(private CreateApplicabilityOnlySuccessor $successor) {}

    /**
     * @param  list<OrganisationalLocation>  $locations
     * @return array{Document, DocumentGovernanceCommand, bool}
     */
    public function handle(
        Document $predecessor,
        User $actor,
        string $idempotencyKey,
        CarbonInterface $effectiveFrom,
        array $locations,
        string $correlationId,
    ): array {
        $locationIds = collect($locations)->map(fn (OrganisationalLocation $location): string => $location->public_id)
            ->sort()->values()->all();
        $payload = ['effective_from' => $effectiveFrom->toISOString(), 'location_public_ids' => $locationIds];
        $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        [$target, $command, $replayed, $operation, $leaseToken] = DB::transaction(function () use (
            $predecessor, $actor, $idempotencyKey, $effectiveFrom, $locations, $correlationId, $digest,
        ): array {
            DB::table('document_governance_commands')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $predecessor->workspace_id,
                'purpose' => 'applicability_successor',
                'idempotency_key' => $idempotencyKey,
                'actor_user_id' => $actor->id,
                'target_kind' => 'document_version',
                'target_document_id' => $predecessor->id,
                'target_state_at_creation' => $predecessor->governance_status->value,
                'request_payload_digest' => $digest,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $commandQuery = DocumentGovernanceCommand::query()
                ->where('workspace_id', $predecessor->workspace_id)
                ->where('purpose', 'applicability_successor')
                ->where('idempotency_key', $idempotencyKey);
            $command = $commandQuery->firstOrFail();
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT lock_document_version_governance_command(?)', [$command->id]);
                $command = $commandQuery->firstOrFail();
            } else {
                $command = $commandQuery->lockForUpdate()->firstOrFail();
            }
            if (
                $command->actor_user_id !== $actor->id
                || $command->target_document_id !== $predecessor->id
                || ! hash_equals($command->request_payload_digest, $digest)
            ) {
                throw new DocumentGovernanceIdempotencyConflict('The idempotency key is already bound to a different governance request.');
            }
            if ($command->status === 'completed') {
                return [$command->resultDocument()->firstOrFail(), $command, true, null, null];
            }
            if ($command->result_document_id !== null) {
                throw new DocumentGovernanceException('The applicability-only successor operation is already in progress.');
            }

            [$target, $operation, $leaseToken] = $this->successor->prepare(
                $predecessor, $actor, $effectiveFrom, $locations, $correlationId,
            );
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT bind_document_version_governance_command_result(?, ?)', [$command->id, $target->id]);
            } else {
                $command->forceFill(['result_document_id' => $target->id])->save();
            }

            return [$target, $command->refresh(), false, $operation, $leaseToken];
        });
        if ($replayed) {
            return [$target, $command, true];
        }

        $result = $this->successor->finish($predecessor, $target, $operation, $leaseToken, $correlationId);
        $command = DB::transaction(function () use ($command, $result): DocumentGovernanceCommand {
            if (DB::getDriverName() === 'pgsql') {
                if (! in_array($result->status, [DocumentStatus::Queued, DocumentStatus::Processing, DocumentStatus::Indexed], true)) {
                    throw new DocumentGovernanceException('The successor did not reach a durable materialisation state.');
                }
                DB::select('SELECT complete_document_version_governance_command(?, ?)', [$command->id, $result->id]);

                return $command->refresh();
            }
            $locked = DocumentGovernanceCommand::query()->whereKey($command->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'completed') {
                if (! in_array($result->status, [DocumentStatus::Queued, DocumentStatus::Processing, DocumentStatus::Indexed], true)) {
                    throw new DocumentGovernanceException('The successor did not reach a durable materialisation state.');
                }
                $locked->forceFill(['status' => 'completed', 'result_document_id' => $result->id])->save();
            }

            return $locked->refresh();
        });

        return [$result, $command, false];
    }
}
