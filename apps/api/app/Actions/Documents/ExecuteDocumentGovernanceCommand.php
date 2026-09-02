<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\DocumentGovernanceIdempotencyConflict;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceCommand;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ExecuteDocumentGovernanceCommand
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(): Document  $execute
     * @return array{Document, DocumentGovernanceCommand, bool}
     */
    public function handle(
        Document $target,
        User $actor,
        string $purpose,
        string $idempotencyKey,
        array $payload,
        Closure $execute,
    ): array {
        return DB::transaction(function () use ($target, $actor, $purpose, $idempotencyKey, $payload, $execute): array {
            DocumentFamily::query()->lockForUpdate()->findOrFail($target->document_family_id);
            $currentTarget = Document::query()->findOrFail($target->id);
            ksort($payload);
            $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            DB::table('document_governance_commands')->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $currentTarget->workspace_id,
                'purpose' => $purpose,
                'idempotency_key' => $idempotencyKey,
                'actor_user_id' => $actor->id,
                'target_kind' => 'document_version',
                'target_document_id' => $currentTarget->id,
                'target_state_at_creation' => $currentTarget->governance_status->value,
                'request_payload_digest' => $digest,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $command = DocumentGovernanceCommand::query()
                ->where('workspace_id', $currentTarget->workspace_id)
                ->where('purpose', $purpose)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            if (
                $command->actor_user_id !== $actor->id
                || $command->target_document_id !== $currentTarget->id
                || ! hash_equals($command->request_payload_digest, $digest)
            ) {
                throw new DocumentGovernanceIdempotencyConflict('The idempotency key is already bound to a different governance request.');
            }

            if ($command->status === 'completed') {
                return [$command->resultDocument()->firstOrFail(), $command, true];
            }

            $result = $execute();
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT complete_document_version_governance_command(?, ?)', [$command->id, $result->id]);
            } else {
                $command->forceFill(['status' => 'completed', 'result_document_id' => $result->id])->save();
            }

            return [$result, $command->refresh(), false];
        });
    }
}
