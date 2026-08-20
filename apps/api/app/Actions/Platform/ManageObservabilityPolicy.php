<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\ObservabilityDeploymentAttempt;
use App\Models\ObservabilityPolicyTarget;
use App\Models\ObservabilityPolicyVersion;
use App\Models\PlatformAdministrationAuditEvent;
use App\Models\User;
use App\Support\Platform\ObservabilityPolicyManifest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ManageObservabilityPolicy
{
    public function __construct(private ObservabilityPolicyManifest $manifest) {}

    /** @param array<string, int|float> $values */
    public function create(User $actor, array $values, string $correlationId): ObservabilityPolicyVersion
    {
        return DB::transaction(function () use ($actor, $values, $correlationId): ObservabilityPolicyVersion {
            ObservabilityPolicyVersion::query()->where('environment', app()->environment())->lockForUpdate()->get();
            $version = ((int) ObservabilityPolicyVersion::query()->where('environment', app()->environment())->max('version')) + 1;
            $policy = ObservabilityPolicyVersion::query()->create([
                'public_id' => (string) Str::uuid(),
                'environment' => app()->environment(),
                'version' => $version,
                'manifest_version' => ObservabilityPolicyManifest::VERSION,
                'manifest_digest' => $this->manifest->digest(),
                'desired_values' => $values,
                'requested_by_user_id' => $actor->id,
                'correlation_id' => $correlationId,
            ]);
            foreach ($this->manifest->targets() as $setting => $targets) {
                if (! array_key_exists($setting, $values)) {
                    throw new RuntimeException('The desired policy is incomplete.');
                }
                foreach ($targets as $target) {
                    $planId = (string) Str::uuid();
                    $policy->targets()->create([
                        'setting_key' => $setting,
                        'target' => $target,
                        'desired_value' => ['value' => $values[$setting]],
                        'plan_id' => $planId,
                        'expected_digest' => $this->manifest->valueDigest(
                            app()->environment(), $version, $setting, $target,
                            $values[$setting], $planId,
                        ),
                        'status' => 'PENDING',
                    ]);
                }
            }
            PlatformAdministrationAuditEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'actor_kind' => 'user',
                'actor_identifier' => $actor->public_id,
                'action' => 'observability.policy.desired',
                'target_type' => 'observability_policy_version',
                'target_public_id' => $policy->public_id,
                'before' => null,
                'after' => ['environment' => app()->environment(), 'version' => $version, 'manifest_digest' => $policy->manifest_digest],
                'correlation_id' => $correlationId,
                'occurred_at' => CarbonImmutable::now(),
            ]);

            return $policy->load('targets');
        });
    }

    /** @return array<string, mixed> */
    public function authorizePlan(array $identity): array
    {
        return DB::transaction(function () use ($identity): array {
            $target = $this->lockedTarget($identity);
            $latest = ObservabilityPolicyVersion::query()->where('environment', $identity['Environment'])->max('version');
            if ($target->policyVersion->version !== (int) $latest) {
                throw new RuntimeException('The desired policy version is superseded.');
            }
            $target->forceFill([
                'current_attempt_id' => strtolower($identity['Deployment-Attempt-ID']),
                'status' => 'PENDING',
                'observed_value' => null,
                'reconciled_at' => null,
            ])->save();

            return [
                'protocol_version' => 'or1',
                'environment' => $target->policyVersion->environment,
                'policy_version' => $target->policyVersion->version,
                'setting_key' => $target->setting_key,
                'target' => $target->target,
                'deployment_attempt_id' => $target->current_attempt_id,
                'plan_id' => $target->plan_id,
                'desired_value' => $target->desired_value['value'],
                'expected_digest' => $target->expected_digest,
            ];
        });
    }

    /** @param array<string, mixed> $payload @return array{delivery: string, derived_state: string, applied: bool} */
    public function acknowledge(array $identity, array $payload): array
    {
        return DB::transaction(function () use ($identity, $payload): array {
            $target = $this->lockedTarget($identity);
            $attemptId = strtolower($identity['Deployment-Attempt-ID']);
            $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $existing = ObservabilityDeploymentAttempt::query()->where('deployment_attempt_id', $attemptId)->first();
            if ($existing !== null) {
                if ($existing->policy_target_id !== $target->id
                    || ! hash_equals($existing->plan_digest, $target->expected_digest)
                    || ! hash_equals($existing->payload_digest, $digest)) {
                    throw new RuntimeException('A conflicting acknowledgement was rejected.');
                }

                return ['delivery' => 'idempotent', 'derived_state' => $target->status, 'applied' => $existing->derived_state_applied];
            }
            $outcome = $payload['outcome'] ?? null;
            $observed = $payload['observed_value'] ?? null;
            $verified = $payload['effective_configuration_verified'] ?? null;
            if (! in_array($outcome, ['SUCCEEDED', 'FAILED'], true)
                || ! is_bool($verified)
                || ! hash_equals($target->expected_digest, (string) ($payload['expected_digest'] ?? ''))
                || ($outcome === 'SUCCEEDED' && $observed !== ($target->desired_value['value'] ?? null))) {
                throw new RuntimeException('The acknowledgement does not match the reconciliation plan.');
            }
            $latest = (int) ObservabilityPolicyVersion::query()->where('environment', $identity['Environment'])->max('version');
            $current = hash_equals((string) $target->current_attempt_id, $attemptId)
                && $target->policyVersion->version === $latest;
            $state = $outcome === 'FAILED' ? 'FAILED' : ($verified ? 'ACTIVE' : 'PENDING');
            $attempt = ObservabilityDeploymentAttempt::query()->create([
                'deployment_attempt_id' => $attemptId,
                'policy_target_id' => $target->id,
                'plan_digest' => $target->expected_digest,
                'payload_digest' => $digest,
                'observed_value' => ['value' => $observed],
                'outcome' => $outcome,
                'failure_category' => is_string($payload['failure_category'] ?? null) ? $payload['failure_category'] : null,
                'correlation_id' => strtolower($identity['Correlation-ID']),
                'applied_at' => CarbonImmutable::parse((string) ($payload['applied_at'] ?? '')),
                'derived_state_applied' => $current,
            ]);
            if ($current) {
                $target->forceFill([
                    'status' => $state,
                    'observed_value' => ['value' => $observed],
                    'reconciled_at' => $attempt->applied_at,
                ])->save();
            }

            return ['delivery' => 'recorded', 'derived_state' => $target->status, 'applied' => $current];
        });
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        $policy = ObservabilityPolicyVersion::query()
            ->with(['targets' => fn ($query) => $query->orderBy('setting_key')->orderBy('target')])
            ->where('environment', app()->environment())
            ->latest('version')->first();
        if ($policy === null) {
            return null;
        }
        $settings = $policy->targets->groupBy('setting_key')->map(function ($targets, string $setting): array {
            $states = $targets->pluck('status')->all();
            $status = in_array('FAILED', $states, true) ? 'FAILED' : (collect($states)->every(fn (string $state): bool => $state === 'ACTIVE') ? 'ACTIVE' : 'PENDING');

            return [
                'setting_key' => $setting,
                'desired_value' => $targets->first()->desired_value['value'],
                'status' => $status,
                'targets' => $targets->map(fn (ObservabilityPolicyTarget $target): array => [
                    'target' => $target->target,
                    'plan_id' => $target->plan_id,
                    'expected_digest' => $target->expected_digest,
                    'current_attempt_id' => $target->current_attempt_id,
                    'status' => $target->status,
                    'reconciled_at' => $target->reconciled_at?->toIso8601String(),
                ])->values()->all(),
            ];
        })->values();

        return [
            'public_id' => $policy->public_id,
            'environment' => $policy->environment,
            'version' => $policy->version,
            'manifest_version' => $policy->manifest_version,
            'manifest_digest' => $policy->manifest_digest,
            'active_settings' => $settings->where('status', 'ACTIVE')->count(),
            'total_settings' => $settings->count(),
            'fully_active' => $settings->every(fn (array $setting): bool => $setting['status'] === 'ACTIVE'),
            'settings' => $settings->all(),
        ];
    }

    private function lockedTarget(array $identity): ObservabilityPolicyTarget
    {
        $target = ObservabilityPolicyTarget::query()
            ->with('policyVersion')
            ->where('setting_key', $identity['Setting-Key'])
            ->where('target', $identity['Target'])
            ->where('plan_id', strtolower($identity['Plan-ID']))
            ->whereHas('policyVersion', fn ($query) => $query
                ->where('environment', $identity['Environment'])
                ->where('version', (int) $identity['Policy-Version']))
            ->lockForUpdate()->first();
        if ($target === null) {
            throw new RuntimeException('The reconciliation plan identity is invalid.');
        }

        return $target;
    }
}
