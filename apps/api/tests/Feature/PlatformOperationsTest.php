<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Platform\ManagePlatformAdministrator;
use App\Contracts\Platform\OperationalMetricsReader;
use App\Enums\PlatformRole;
use App\Models\PlatformAdministrationAuditEvent;
use App\Models\PlatformAdministrationCommand;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Platform\PlatformAdministrationCredentialRegistry;
use App\Services\Platform\PrometheusOperationalMetricsReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_authority_is_live_and_independent_of_workspace_roles_in_both_directions(): void
    {
        $owner = User::factory()->create();
        Workspace::factory()->withOwner($owner)->create();
        $platformAdministrator = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $this->app->instance(OperationalMetricsReader::class, new class implements OperationalMetricsReader
        {
            public function snapshot(): array
            {
                return ['status' => 'available'];
            }
        });

        $this->actingAs($owner)->getJson('/api/platform/operations/access')->assertForbidden();
        $this->actingAs($platformAdministrator)->getJson('/api/platform/operations/access')
            ->assertOk()->assertJsonPath('data.access', 'administrator');
        $this->actingAs($platformAdministrator)->getJson('/api/workspaces')->assertOk()->assertJsonCount(0, 'data');

        DB::table('users')->where('id', $platformAdministrator->id)->update(['platform_role' => null]);
        $this->actingAs($platformAdministrator)->getJson('/api/platform/operations/access')->assertForbidden();
    }

    public function test_platform_role_mutation_is_atomic_idempotent_audited_and_protects_the_final_administrator(): void
    {
        $first = User::factory()->create();
        $replacement = User::factory()->create();
        $action = app(ManagePlatformAdministrator::class);
        $key = (string) Str::uuid();

        $result = $action->handle('bootstrap', $first->public_id, null, $key, 'deploy-key:v1', (string) Str::uuid());
        $this->assertSame(PlatformRole::Administrator->value, $result['platform_role']);
        $action->handle('bootstrap', $first->public_id, null, $key, 'deploy-key:v1', (string) Str::uuid());
        $this->assertSame(1, PlatformAdministrationCommand::query()->count());
        $this->assertSame(1, PlatformAdministrationAuditEvent::query()->count());

        try {
            $action->handle('revoke', $first->public_id, null, (string) Str::uuid(), 'deploy-key:v1', (string) Str::uuid());
            $this->fail('The final administrator revocation should fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('final active', $exception->getMessage());
        }
        $this->assertSame(PlatformRole::Administrator, $first->fresh()->platform_role);

        $action->handle('revoke', $first->public_id, $replacement->public_id, (string) Str::uuid(), 'deploy-key:v1', (string) Str::uuid());
        $this->assertNull($first->fresh()->platform_role);
        $this->assertSame(PlatformRole::Administrator, $replacement->fresh()->platform_role);
        $serialized = PlatformAdministrationCommand::query()->get()->toJson().PlatformAdministrationAuditEvent::query()->get()->toJson();
        $this->assertStringNotContainsString('credential-secret', $serialized);
        $this->assertDatabaseMissing('platform_administration_audit_events', ['target_type' => 'workspace']);
    }

    public function test_deployment_credential_is_versioned_rotatable_and_rejected_before_mutation(): void
    {
        config(['platform.administration_credentials' => [
            ['key_id' => 'active-key', 'version' => 'v2', 'secret' => base64_encode('credential-secret'), 'revoked' => false],
            ['key_id' => 'revoked-key', 'version' => 'v1', 'secret' => base64_encode('old-secret'), 'revoked' => true],
        ]]);
        $registry = app(PlatformAdministrationCredentialRegistry::class);
        $this->assertSame(['key_id' => 'active-key', 'version' => 'v2'], $registry->authenticate('active-key', 'credential-secret'));

        foreach ([['active-key', 'wrong'], ['revoked-key', 'old-secret']] as [$key, $secret]) {
            try {
                $registry->authenticate($key, $secret);
                $this->fail('Invalid credentials must fail closed.');
            } catch (RuntimeException) {
                $this->assertSame(0, PlatformAdministrationCommand::query()->count());
            }
        }
    }

    public function test_non_browser_command_reads_secret_from_protected_prompt(): void
    {
        config(['platform.administration_credentials' => [[
            'key_id' => 'deploy-key', 'version' => 'v1', 'secret' => base64_encode('credential-secret'), 'revoked' => false,
        ]]]);
        $user = User::factory()->create();

        $this->artisan('platform-administrator:manage', [
            'operation' => 'bootstrap',
            'user_public_id' => $user->public_id,
            '--key-id' => 'deploy-key',
            '--idempotency-key' => (string) Str::uuid(),
        ])->expectsQuestion('Platform administration deployment credential', 'credential-secret')
            ->assertSuccessful();

        $this->assertSame(PlatformRole::Administrator, $user->fresh()->platform_role);
        $this->assertStringNotContainsString('credential-secret', PlatformAdministrationCommand::query()->sole()->toJson());
    }

    public function test_disable_and_delete_invalidate_database_sessions_and_public_identity_is_immutable(): void
    {
        $user = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        DB::table('sessions')->insert([
            'id' => 'test-session', 'user_id' => $user->id, 'payload' => 'content-free', 'last_activity' => time(),
        ]);
        $user->forceFill(['disabled_at' => now()])->save();
        $this->assertDatabaseMissing('sessions', ['id' => 'test-session']);
        $this->actingAs($user)->getJson('/api/auth/user')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'account_disabled');

        $deleted = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'deleted-session', 'user_id' => $deleted->id, 'payload' => 'content-free', 'last_activity' => time(),
        ]);
        $deleted->delete();
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-session']);

        $this->expectException(LogicException::class);
        $user->forceFill(['public_id' => (string) Str::uuid()])->save();
    }

    public function test_metrics_reader_uses_only_predefined_queries_and_fails_soft_without_fabricating_zero(): void
    {
        Cache::forget('platform:operational-metrics:v2');
        config(['platform.metrics.base_url' => 'http://metrics.test', 'platform.metrics.cache_seconds' => 1]);
        $queries = [];
        $failureMode = false;
        Http::fake(function ($request) use (&$queries, &$failureMode) {
            $queries[] = $request->data()['query'] ?? null;

            if ($failureMode) {
                return Http::response([], 503);
            }

            return Http::response(['status' => 'success', 'data' => ['result' => array_fill(0, 60, [
                'metric' => [
                    'rag_operation_stage' => 'retrieval',
                    'workspace_id' => 'must-not-cross-the-adapter',
                ],
                'value' => [time(), '1.25'],
            ])]]);
        });
        $admin = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $this->actingAs($admin)->getJson('/api/platform/operations/health?query=up%7Bworkspace%3Dsecret%7D')
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.health_status', 'degraded');
        $this->assertNotEmpty($queries);
        $this->assertStringNotContainsString('workspace=secret', implode(' ', $queries));
        $response = $this->actingAs($admin)->getJson('/api/platform/operations/health')->json('data');
        $this->assertCount(50, $response['metrics']['operation_rate']['values']);
        $this->assertStringNotContainsString('must-not-cross-the-adapter', json_encode($response, JSON_THROW_ON_ERROR));

        Cache::forget('platform:operational-metrics:v2');
        $failureMode = true;
        $snapshot = app(PrometheusOperationalMetricsReader::class)->snapshot();
        $this->assertSame('unavailable', $snapshot['status']);
        $this->assertSame('unknown', $snapshot['health_status']);
        foreach ($snapshot['metrics'] as $metric) {
            $this->assertSame('unavailable', $metric['status']);
            $this->assertSame([], $metric['values']);
        }
    }

    public function test_signup_cannot_self_assign_platform_authority(): void
    {
        config(['fortify.features' => [Features::registration()]]);
        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new-user@example.test',
            'password' => 'Correct-Horse-9!',
            'password_confirmation' => 'Correct-Horse-9!',
            'platform_role' => PlatformRole::Administrator->value,
        ])->assertCreated();

        $this->assertNull(User::query()->where('email', 'new-user@example.test')->sole()->platform_role);
    }

    public function test_slo_and_alert_adapter_preserve_controlled_outcomes_and_filter_specialist_payloads(): void
    {
        Cache::forget('platform:operational-metrics:v2');
        config([
            'platform.metrics.base_url' => 'http://metrics.test',
            'platform.metrics.alertmanager_url' => 'http://alerts.test',
            'platform.metrics.alertmanager_public_url' => 'http://127.0.0.1:9093',
        ]);
        $queries = [];
        Http::fake(function ($request) use (&$queries) {
            if (str_starts_with($request->url(), 'http://alerts.test')) {
                return Http::response([[
                    'labels' => [
                        'alertname' => 'DolvedDependencyUnavailable',
                        'severity' => 'urgent',
                        'subsystem' => 'dependency',
                        'workspace_id' => 'must-not-cross',
                    ],
                    'annotations' => [
                        'impact' => 'A dependency is unavailable.',
                        'runbook_url' => 'https://example.test/runbook',
                        'raw_payload' => 'must-not-cross',
                    ],
                    'status' => ['state' => 'active'],
                    'startsAt' => '2026-08-20T12:00:00Z',
                ]]);
            }
            $queries[] = (string) ($request->data()['query'] ?? '');

            return Http::response(['status' => 'success', 'data' => ['result' => [[
                'metric' => [], 'value' => [time(), '0.995'],
            ]]]]);
        });

        $snapshot = app(PrometheusOperationalMetricsReader::class)->snapshot();

        $this->assertCount(2, $snapshot['slos']);
        $this->assertTrue($snapshot['slos'][0]['compliant']);
        $this->assertSame('available', $snapshot['alerts']['status']);
        $this->assertSame('DolvedDependencyUnavailable', $snapshot['alerts']['values'][0]['name']);
        $serialized = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('must-not-cross', $serialized);
        $conversationQuery = implode(' ', array_filter($queries, fn (string $query): bool => str_contains($query, 'generation_run')));
        $this->assertStringContainsString('retrieval_no_answer', $conversationQuery);
        $this->assertStringContainsString('clarification_required', $conversationQuery);
        $this->assertStringNotContainsString('cancelled', $conversationQuery);
    }
}
