<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Conversation\OrchestrateDifyConversationRun;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Enums\ChecksumVerificationStatus;
use App\Jobs\ExecuteGenerationRun;
use App\Models\DifyDocumentBinding;
use App\Models\DifyWorkspaceBinding;
use App\Models\Document;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Telemetry\TelemetryAttributeAllowlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Tests\TestCase;

final class DifyDeliveryBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_access_is_creator_only_even_for_another_workspace_member(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->workspaceFixture();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $conversation = app(CreateConversation::class)->handle($workspace, $owner);

        $this->actingAs($member)
            ->getJson("/api/workspaces/{$workspace->public_id}/conversations")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}")
            ->assertNotFound();
        $this->postJson("/api/workspaces/{$workspace->public_id}/conversations/{$conversation->public_id}/messages", [
            'message' => 'Cross-user question',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_conversation_creation_accepts_only_an_explicit_site_from_the_same_workspace(): void
    {
        [$user, $workspace] = $this->workspaceFixture();
        $site = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Cedar House', 'kind' => 'site']);
        $other = Workspace::factory()->withOwner($user)->create();
        $foreignSite = OrganisationalLocation::factory()->for($other)->create();

        $this->actingAs($user)
            ->postJson("/api/workspaces/{$workspace->public_id}/conversations", [
                'selected_location_id' => $site->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.site_context.scope_key', $site->public_id)
            ->assertJsonPath('data.site_context.name', 'Cedar House');

        $this->postJson("/api/workspaces/{$workspace->public_id}/conversations", [
            'selected_location_id' => $foreignSite->public_id,
        ])->assertNotFound();

        $this->postJson("/api/workspaces/{$workspace->public_id}/conversations")
            ->assertCreated()
            ->assertJsonPath('data.site_context.scope_key', 'global');
    }

    public function test_dify_run_sends_trusted_site_once_and_persists_honest_retrieved_sources(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        $site = OrganisationalLocation::factory()->for($workspace)->create(['name' => 'Cedar House', 'kind' => 'site']);
        DifyWorkspaceBinding::query()->create([
            'workspace_id' => $workspace->id,
            'app_id' => (string) Str::uuid(),
            'dataset_id' => (string) Str::uuid(),
            'scope_metadata_id' => (string) Str::uuid(),
            'enabled' => true,
        ]);
        $document = Document::factory()->for($workspace)->for($user, 'createdBy')->indexed()->create([
            'source_filename' => 'cedar-policy.pdf',
            'source_checksum_sha256' => str_repeat('a', 64),
            'checksum_verification_status' => ChecksumVerificationStatus::Verified,
        ]);
        $remoteDocumentId = (string) Str::uuid();
        $datasetId = (string) Str::uuid();
        DifyDocumentBinding::query()->create([
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
            'dataset_id' => $datasetId,
            'scope_key' => 'global',
            'remote_document_id' => $remoteDocumentId,
            'remote_batch_id' => null,
            'remote_name' => 'dolved-document.txt',
            'sync_status' => 'available',
            'source_checksum_sha256' => str_repeat('a', 64),
            'synced_at' => now(),
        ]);
        config([
            'dify.base_url' => 'http://dify.test',
            'dify.app_api_key' => 'server-secret',
        ]);
        $remoteConversationId = (string) Str::uuid();
        $remoteMessageId = (string) Str::uuid();
        $remoteSegmentId = (string) Str::uuid();
        Http::preventStrayRequests();
        Http::fake(['http://dify.test/v1/chat-messages' => Http::response([
            'answer' => 'The Cedar rule applies.',
            'conversation_id' => $remoteConversationId,
            'message_id' => $remoteMessageId,
            'task_id' => (string) Str::uuid(),
            'metadata' => [
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'total_price' => '0.003', 'currency' => 'USD', 'latency' => 1.25],
                'retriever_resources' => [[
                    'dataset_id' => $datasetId,
                    'document_id' => $remoteDocumentId,
                    'segment_id' => $remoteSegmentId,
                    'document_name' => '../cedar-policy.pdf',
                    'content' => '  Retrieved Cedar passage.  ',
                    'score' => 0.75,
                    'position' => 1,
                ]],
            ],
        ])]);

        $conversation = app(CreateConversation::class)->handle($workspace, $user, $site);
        $run = app(SubmitConversationMessage::class)->handle(
            $conversation, $user, 'What is the rule?', (string) Str::uuid(),
        );
        app(OrchestrateDifyConversationRun::class)->handle($run->id);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($site): bool {
            return $request->url() === 'http://dify.test/v1/chat-messages'
                && $request->hasHeader('Authorization', 'Bearer server-secret')
                && $request['response_mode'] === 'blocking'
                && $request['inputs']['trusted_site'] === $site->public_id
                && $request['query'] === 'What is the rule?'
                && str_starts_with($request['user'], 'dolved_')
                && ! str_contains($request['user'], (string) $site->public_id);
        });
        $run->refresh();
        $this->assertSame('completed', $run->status->value);
        $this->assertSame('completed', $run->provider_dispatch_state);
        $this->assertSame($remoteMessageId, $run->provider_message_id);
        $this->assertSame($remoteConversationId, $conversation->difySession->remote_conversation_id);
        $source = $run->generatedAnswer->retrievedSources()->firstOrFail();
        $this->assertSame($document->id, $source->document_id);
        $this->assertSame('cedar-policy.pdf', $source->display_name);
        $this->assertSame('Retrieved Cedar passage.', $source->retrieved_text);
        $this->assertSame(hash('sha256', 'Retrieved Cedar passage.'), $source->content_digest);
        $this->assertSame('0.750000000000', $source->score);
        $this->assertSame('0.00300000', $run->generatedAnswer->cost_usd);
    }

    public function test_execute_job_routes_only_enabled_workspaces_to_dify(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->workspaceFixture();
        DifyWorkspaceBinding::query()->create([
            'workspace_id' => $workspace->id,
            'app_id' => (string) Str::uuid(),
            'dataset_id' => (string) Str::uuid(),
            'scope_metadata_id' => (string) Str::uuid(),
            'enabled' => true,
        ]);
        config(['dify.base_url' => 'http://dify.test', 'dify.app_api_key' => 'secret']);
        Http::fake(['http://dify.test/v1/chat-messages' => Http::response([
            'answer' => 'Organisation-wide answer.',
            'conversation_id' => (string) Str::uuid(),
            'message_id' => (string) Str::uuid(),
            'metadata' => ['usage' => [], 'retriever_resources' => []],
        ])]);
        $conversation = app(CreateConversation::class)->handle($workspace, $user);
        $run = app(SubmitConversationMessage::class)->handle($conversation, $user, 'Question', (string) Str::uuid());

        app(ExecuteGenerationRun::class, ['generationRunId' => $run->id])->handle(
            app(OrchestrateConversationRun::class),
            app(OrchestrateDifyConversationRun::class),
            app(TracerProviderInterface::class),
            app(TelemetryAttributeAllowlist::class),
        );

        $this->assertSame('completed', $run->fresh()->status->value);
        Http::assertSent(fn (Request $request): bool => $request['inputs']['trusted_site'] === 'global');
    }

    /** @return array{User, Workspace} */
    private function workspaceFixture(): array
    {
        $user = User::factory()->create();

        return [$user, Workspace::factory()->withOwner($user)->create()];
    }
}
