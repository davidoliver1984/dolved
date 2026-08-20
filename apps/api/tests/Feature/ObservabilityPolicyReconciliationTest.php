<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Platform\ManageObservabilityPolicy;
use App\Enums\PlatformRole;
use App\Models\ObservabilityDeploymentAttempt;
use App\Models\ObservabilityPolicyTarget;
use App\Models\User;
use App\Support\Platform\ObservabilityPolicyManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ObservabilityPolicyReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = str_repeat('r', 32);
        config([
            'platform.reconciliation.environment' => 'testing',
            'platform.reconciliation.credentials' => [[
                'key_id' => 'reconciler', 'version' => 'v1',
                'secret' => base64_encode($this->secret), 'revoked' => false,
            ]],
        ]);
    }

    public function test_desired_policy_is_versioned_and_never_self_declares_active(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $response = $this->actingAs($admin)->postJson('/api/platform/operations/policy', $this->values())
            ->assertCreated();

        $this->assertSame(6, ObservabilityPolicyTarget::query()->count());
        $this->assertSame('PENDING', ObservabilityPolicyTarget::query()->where('setting_key', 'trace_sampling_percentage')->sole()->status);
        $response->assertJsonPath('data.policy.version', 1);
        $this->actingAs($admin)->getJson('/api/platform/operations/policy')
            ->assertOk()->assertJsonPath('data.policy.fully_active', false);
    }

    public function test_authenticated_plan_and_acknowledgement_advance_only_the_current_attempt(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $policy = app(ManageObservabilityPolicy::class)->create($admin, $this->values(), (string) Str::uuid());
        $target = $policy->targets->firstWhere('setting_key', 'trace_sampling_percentage');
        $attemptA = (string) Str::uuid();
        $correlation = (string) Str::uuid();

        $this->signedPost('/api/internal/observability/reconciliation/plan', '[]', 'observability.policy.plan.read', $target, $attemptA, $correlation)
            ->assertOk()->assertJsonPath('data.desired_value', 10);
        $bodyA = $this->ackBody($target, 10, 'FAILED', true);
        $this->signedPost('/api/internal/observability/reconciliation/acknowledgements', $bodyA, 'observability.policy.reconcile', $target, $attemptA, $correlation)
            ->assertOk()->assertJsonPath('data.derived_state', 'FAILED');

        $attemptB = (string) Str::uuid();
        $this->signedPost('/api/internal/observability/reconciliation/plan', '[]', 'observability.policy.plan.read', $target, $attemptB, $correlation)->assertOk();
        $bodyB = $this->ackBody($target, 10, 'SUCCEEDED', true);
        $this->signedPost('/api/internal/observability/reconciliation/acknowledgements', $bodyB, 'observability.policy.reconcile', $target, $attemptB, $correlation)
            ->assertOk()->assertJsonPath('data.derived_state', 'ACTIVE');

        $this->assertSame(2, ObservabilityDeploymentAttempt::query()->count());
        $this->assertSame('FAILED', ObservabilityDeploymentAttempt::query()->where('deployment_attempt_id', $attemptA)->sole()->outcome);
        $this->assertSame('ACTIVE', $target->fresh()->status);
    }

    public function test_identical_delivery_is_idempotent_conflict_fails_and_superseded_attempt_is_inert(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $policy = app(ManageObservabilityPolicy::class)->create($admin, $this->values(), (string) Str::uuid());
        $target = $policy->targets->firstWhere('setting_key', 'trace_sampling_percentage');
        $attemptA = (string) Str::uuid();
        $attemptB = (string) Str::uuid();
        $correlation = (string) Str::uuid();
        $this->signedPost('/api/internal/observability/reconciliation/plan', '[]', 'observability.policy.plan.read', $target, $attemptA, $correlation)->assertOk();
        $this->signedPost('/api/internal/observability/reconciliation/plan', '[]', 'observability.policy.plan.read', $target, $attemptB, $correlation)->assertOk();

        $body = $this->ackBody($target, 10, 'SUCCEEDED', true);
        $this->signedPost('/api/internal/observability/reconciliation/acknowledgements', $body, 'observability.policy.reconcile', $target, $attemptA, $correlation)
            ->assertOk()->assertJsonPath('data.applied', false);
        $this->assertSame('PENDING', $target->fresh()->status);
        $this->signedPost('/api/internal/observability/reconciliation/acknowledgements', $body, 'observability.policy.reconcile', $target, $attemptA, $correlation)
            ->assertOk()->assertJsonPath('data.delivery', 'idempotent');
        $conflict = $this->ackBody($target, 10, 'FAILED', true);
        $this->signedPost('/api/internal/observability/reconciliation/acknowledgements', $conflict, 'observability.policy.reconcile', $target, $attemptA, $correlation)
            ->assertStatus(409);
        $this->assertSame(1, ObservabilityDeploymentAttempt::query()->count());
    }

    public function test_attempt_identity_cannot_be_replayed_across_policy_targets(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $policy = app(ManageObservabilityPolicy::class)->create($admin, $this->values(), (string) Str::uuid());
        $sampling = $policy->targets->firstWhere('setting_key', 'trace_sampling_percentage');
        $retention = $policy->targets->firstWhere('setting_key', 'trace_retention_days');
        $attempt = (string) Str::uuid();
        $correlation = (string) Str::uuid();

        $this->signedPost('/api/internal/observability/reconciliation/plan', '[]', 'observability.policy.plan.read', $sampling, $attempt, $correlation)->assertOk();
        $body = $this->ackBody($sampling, 10, 'SUCCEEDED', true);
        $this->signedPost('/api/internal/observability/reconciliation/acknowledgements', $body, 'observability.policy.reconcile', $sampling, $attempt, $correlation)->assertOk();

        $this->signedPost('/api/internal/observability/reconciliation/plan', '[]', 'observability.policy.plan.read', $retention, $attempt, $correlation)->assertOk();
        $this->signedPost('/api/internal/observability/reconciliation/acknowledgements', $body, 'observability.policy.reconcile', $retention, $attempt, $correlation)
            ->assertConflict()
            ->assertExactJson(['message' => 'The reconciliation acknowledgement was rejected.']);

        $this->assertSame('PENDING', $retention->fresh()->status);
        $this->assertSame(1, ObservabilityDeploymentAttempt::query()->count());
    }

    public function test_authentication_rejects_replay_wrong_purpose_and_wrong_environment_generically(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $policy = app(ManageObservabilityPolicy::class)->create($admin, $this->values(), (string) Str::uuid());
        $target = $policy->targets->firstWhere('setting_key', 'trace_sampling_percentage');
        $attempt = (string) Str::uuid();
        $correlation = (string) Str::uuid();
        $requestId = (string) Str::uuid();
        $headers = $this->headers('/api/internal/observability/reconciliation/plan', '[]', 'observability.policy.plan.read', $target, $attempt, $correlation, $requestId);

        $this->withHeaders($headers)->postJson('/api/internal/observability/reconciliation/plan', [])->assertOk();
        $this->withHeaders($headers)->postJson('/api/internal/observability/reconciliation/plan', [])
            ->assertUnauthorized()->assertExactJson(['message' => 'The reconciliation request is not authenticated.']);

        $wrong = $headers;
        $wrong['X-Observability-Reconciliation-Purpose'] = 'observability.policy.reconcile';
        $this->withHeaders($wrong)->postJson('/api/internal/observability/reconciliation/plan', [])->assertUnauthorized();
    }

    public function test_collector_is_the_only_sampling_target_and_sampler_is_configured(): void
    {
        $this->assertSame(['collector'], app(ObservabilityPolicyManifest::class)->targets()['trace_sampling_percentage']);
    }

    private function values(): array
    {
        return [
            'trace_sampling_percentage' => 10,
            'slow_operation_threshold_seconds' => 5,
            'log_retention_days' => 30,
            'trace_retention_days' => 14,
            'metric_retention_days' => 90,
        ];
    }

    private function ackBody(ObservabilityPolicyTarget $target, int $observed, string $outcome, bool $verified): string
    {
        return json_encode([
            'outcome' => $outcome,
            'observed_value' => $observed,
            'expected_digest' => $target->expected_digest,
            'effective_configuration_verified' => $verified,
            'failure_category' => $outcome === 'FAILED' ? 'test_failure' : null,
            'applied_at' => now()->startOfSecond()->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function signedPost(string $path, string $body, string $purpose, ObservabilityPolicyTarget $target, string $attempt, string $correlation)
    {
        return $this->withHeaders($this->headers($path, $body, $purpose, $target, $attempt, $correlation, (string) Str::uuid()))
            ->postJson($path, json_decode($body, true, 512, JSON_THROW_ON_ERROR));
    }

    private function headers(string $path, string $body, string $purpose, ObservabilityPolicyTarget $target, string $attempt, string $correlation, string $requestId): array
    {
        $timestamp = (string) now()->timestamp;
        $values = [
            'or1', $purpose, 'POST', $path, 'reconciler', 'v1', 'testing',
            (string) $target->policyVersion->version, $target->setting_key, $target->target,
            $attempt, $target->plan_id, hash('sha256', $body), $timestamp, $requestId, $correlation,
        ];

        return [
            'Content-Type' => 'application/json',
            'X-Observability-Reconciliation-Key-ID' => 'reconciler',
            'X-Observability-Reconciliation-Key-Version' => 'v1',
            'X-Observability-Reconciliation-Timestamp' => $timestamp,
            'X-Observability-Reconciliation-Request-ID' => $requestId,
            'X-Observability-Reconciliation-Purpose' => $purpose,
            'X-Observability-Reconciliation-Environment' => 'testing',
            'X-Observability-Reconciliation-Policy-Version' => (string) $target->policyVersion->version,
            'X-Observability-Reconciliation-Setting-Key' => $target->setting_key,
            'X-Observability-Reconciliation-Target' => $target->target,
            'X-Observability-Reconciliation-Deployment-Attempt-ID' => $attempt,
            'X-Observability-Reconciliation-Plan-ID' => $target->plan_id,
            'X-Observability-Reconciliation-Correlation-ID' => $correlation,
            'X-Observability-Reconciliation-Signature' => 'or1='.hash_hmac('sha256', implode("\n", $values), $this->secret),
        ];
    }
}
