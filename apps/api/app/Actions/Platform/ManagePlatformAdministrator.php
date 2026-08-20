<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Enums\PlatformRole;
use App\Models\PlatformAdministrationAuditEvent;
use App\Models\PlatformAdministrationCommand;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ManagePlatformAdministrator
{
    /** @return array<string, mixed> */
    public function handle(string $operation, string $targetPublicId, ?string $replacementPublicId, string $idempotencyKey, string $actorIdentifier, string $correlationId): array
    {
        $operation = strtolower($operation);
        if (! in_array($operation, ['bootstrap', 'grant', 'revoke', 'recover'], true)) {
            throw new RuntimeException('Unsupported platform administration operation.');
        }

        return DB::transaction(function () use ($operation, $targetPublicId, $replacementPublicId, $idempotencyKey, $actorIdentifier, $correlationId): array {
            $request = ['operation' => $operation, 'replacement' => $replacementPublicId, 'target' => $targetPublicId];
            $digest = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
            $existing = PlatformAdministrationCommand::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof PlatformAdministrationCommand) {
                if ($existing->command_type !== strtoupper($operation) || ! hash_equals($existing->request_digest, $digest)) {
                    throw new RuntimeException('The idempotency key was reused for a different platform administration command.');
                }

                return $existing->result ?? [];
            }

            $users = User::query()
                ->whereIn('public_id', array_values(array_filter([$targetPublicId, $replacementPublicId])))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('public_id');
            $target = $users->get($targetPublicId);
            if (! $target instanceof User) {
                throw new RuntimeException('The target user does not exist.');
            }
            $replacement = $replacementPublicId === null ? null : $users->get($replacementPublicId);
            if ($replacementPublicId !== null && (! $replacement instanceof User || $replacement->is($target) || $replacement->disabled_at !== null)) {
                throw new RuntimeException('The replacement user is not eligible.');
            }

            $activeAdministrators = User::query()
                ->whereNull('disabled_at')
                ->where('platform_role', PlatformRole::Administrator->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $activeCount = $activeAdministrators->count();
            if ($operation === 'bootstrap' && $activeCount !== 0) {
                throw new RuntimeException('Bootstrap is allowed only before an active platform administrator exists.');
            }
            if ($operation === 'recover' && $activeCount !== 0) {
                throw new RuntimeException('Recovery is allowed only when no active platform administrator exists.');
            }
            if (in_array($operation, ['bootstrap', 'grant', 'recover'], true) && $target->disabled_at !== null) {
                throw new RuntimeException('A disabled user cannot become a platform administrator.');
            }
            if ($operation === 'revoke' && $target->platform_role === PlatformRole::Administrator && $target->disabled_at === null && $activeCount <= 1 && ! $replacement instanceof User) {
                throw new RuntimeException('The final active platform administrator cannot be revoked without an eligible replacement.');
            }

            $command = PlatformAdministrationCommand::query()->create([
                'public_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'command_type' => strtoupper($operation),
                'request_digest' => $digest,
                'actor_kind' => 'DEPLOYMENT_CREDENTIAL',
                'actor_identifier' => $actorIdentifier,
                'target_user_public_id' => $targetPublicId,
                'correlation_id' => $correlationId,
            ]);

            if ($replacement instanceof User && $replacement->platform_role !== PlatformRole::Administrator) {
                $this->mutate($replacement, PlatformRole::Administrator, 'platform_administrator_granted', $actorIdentifier, $correlationId);
            }
            $role = $operation === 'revoke' ? null : PlatformRole::Administrator;
            if ($target->platform_role !== $role) {
                $this->mutate($target, $role, $operation === 'revoke' ? 'platform_administrator_revoked' : 'platform_administrator_granted', $actorIdentifier, $correlationId);
            }

            $result = [
                'operation' => $operation,
                'target_user_public_id' => $targetPublicId,
                'replacement_user_public_id' => $replacementPublicId,
                'platform_role' => $role?->value,
            ];
            $command->forceFill(['result' => $result])->save();

            return $result;
        });
    }

    private function mutate(User $user, ?PlatformRole $role, string $action, string $actorIdentifier, string $correlationId): void
    {
        $before = ['platform_role' => $user->platform_role?->value, 'disabled' => $user->disabled_at !== null];
        $user->forceFill(['platform_role' => $role])->save();
        PlatformAdministrationAuditEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'actor_kind' => 'DEPLOYMENT_CREDENTIAL',
            'actor_identifier' => $actorIdentifier,
            'action' => $action,
            'target_type' => 'user',
            'target_public_id' => $user->public_id,
            'before' => $before,
            'after' => ['platform_role' => $role?->value, 'disabled' => $user->disabled_at !== null],
            'correlation_id' => $correlationId,
            'occurred_at' => now(),
        ]);
    }
}
