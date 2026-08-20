<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceActivityEvent;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceUsageEvent;
use App\Support\Usage\RecordWorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class WorkspaceUsageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_is_owner_admin_only_tenant_scoped_bounded_and_distinguishes_unavailable_cost(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->create(['role' => WorkspaceRole::Member]);
        $admin = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($admin)->create(['role' => WorkspaceRole::Admin]);
        $other = Workspace::factory()->withOwner()->create();

        Document::factory()->for($workspace)->indexed()->create(['size_bytes' => 120]);
        Document::factory()->for($workspace)->deleting()->create(['size_bytes' => 999]);
        Document::factory()->for($other)->indexed()->create(['size_bytes' => 800]);
        WorkspaceActivityEvent::query()->create([
            'workspace_id' => $workspace->id,
            'event_kind' => 'user_submission',
            'source_public_id' => (string) Str::uuid(),
            'outcome' => null,
            'occurred_at' => now()->subDay(),
        ]);
        WorkspaceActivityEvent::query()->create([
            'workspace_id' => $other->id,
            'event_kind' => 'user_submission',
            'source_public_id' => (string) Str::uuid(),
            'outcome' => null,
            'occurred_at' => now()->subDay(),
        ]);
        WorkspaceUsageEvent::query()->create([
            'workspace_id' => $workspace->id,
            'scope_type' => 'generation_run',
            'scope_public_id' => (string) Str::uuid(),
            'operation_kind' => 'generation',
            'ordinal' => 0,
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'execution' => 'provider_api',
            'request_count' => 1,
            'retry_count' => 0,
            'input_tokens' => 100,
            'cached_input_tokens' => null,
            'output_tokens' => 20,
            'latency_ms' => 150,
            'cost_usd' => null,
            'cost_basis' => 'unavailable',
            'pricing_snapshot' => null,
            'occurred_at' => now()->subDay(),
        ]);

        $path = "/api/workspaces/{$workspace->public_id}/usage?range=7d";
        $this->actingAs($owner)->getJson($path)->assertOk()
            ->assertJsonPath('data.gauges.active_documents', 1)
            ->assertJsonPath('data.gauges.logical_source_bytes', 120)
            ->assertJsonPath('data.historical.activity.0.aggregate_count', 1)
            ->assertJsonPath('data.historical.usage.0.cost_basis', 'unavailable')
            ->assertJsonPath('data.historical.usage.0.cost_usd', null)
            ->assertJsonPath('data.range.semantics', '[start,end) UTC');
        $this->actingAs($member)->getJson($path)->assertForbidden();
        $this->actingAs($admin)->getJson($path)->assertOk();
        $this->actingAs($owner)->getJson("/api/workspaces/{$other->public_id}/usage")->assertNotFound();
        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/usage?range=forever")->assertUnprocessable();
    }

    public function test_content_free_activity_and_usage_are_idempotent_and_cost_basis_is_fail_closed(): void
    {
        $workspace = Workspace::factory()->withOwner()->create();
        $recorder = app(RecordWorkspaceUsage::class);
        $source = (string) Str::uuid();
        $entry = [[
            'stage' => 'generation',
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'execution' => 'provider_api',
            'request_count' => 1,
            'retry_count' => 0,
            'input_tokens' => 10,
            'cached_input_tokens' => 0,
            'output_tokens' => 2,
            'latency_ms' => 20,
            'cost_usd' => 0.0000065,
            'cost_basis' => 'estimated',
            'pricing_snapshot' => 'openai-test-pricing-v1',
        ]];
        $recorder->activity($workspace->id, 'user_submission', $source);
        $recorder->activity($workspace->id, 'user_submission', $source);
        $recorder->usage($workspace->id, 'generation_run', $source, $entry);
        $recorder->usage($workspace->id, 'generation_run', $source, $entry);
        $recorder->usage($workspace->id, 'generation_run', $source, [[
            'stage' => 'qdrant_dense_search',
            'provider' => 'qdrant',
            'model' => 'rag-platform-vectors-v1',
            'execution' => 'infrastructure',
            'request_count' => 1,
            'retry_count' => 0,
            'input_tokens' => null,
            'cached_input_tokens' => null,
            'output_tokens' => null,
            'latency_ms' => 4,
            'cost_usd' => null,
            'cost_basis' => 'unavailable',
            'pricing_snapshot' => null,
        ]]);
        $this->assertDatabaseCount('workspace_activity_events', 1);
        $this->assertDatabaseCount('workspace_usage_events', 2);

        $this->expectException(InvalidArgumentException::class);
        $recorder->usage($workspace->id, 'generation_run', (string) Str::uuid(), [[...$entry[0], 'cost_basis' => 'unavailable']]);
    }
}
