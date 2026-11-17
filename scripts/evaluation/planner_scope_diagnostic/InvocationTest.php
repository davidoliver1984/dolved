<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceMembership;
use App\Services\Generation\GenerationProfileFactory;
use App\Services\Retrieval\RetrievalCallSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_signed_planner_boundary_without_downstream_or_persistence(): void
    {
        if (! defined('PLANNER_DIAGNOSTIC_LIBRARY_ONLY')) {
            define('PLANNER_DIAGNOSTIC_LIBRARY_ONLY', true);
            require '/diagnostic/invoke.php';
        }
        $workspace = Workspace::factory()->create(['public_id' => '5323cd6b-8b7a-40f9-98b0-9413c75f643e']);
        $user = User::factory()->create(['public_id' => 'ae454ce6-4d91-4c7e-9a40-b4750b696a6b']);
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => WorkspaceRole::Owner]);
        $dense = EmbeddingProfile::factory()->create();
        $space = EmbeddingSpaceGeneration::factory()->available()->create(['embedding_profile_id' => $dense->id, 'dimensions' => $dense->dimensions]);
        $sparse = SparseEmbeddingProfile::factory()->create();
        $ss = SparseSpaceGeneration::factory()->available()->create(['sparse_embedding_profile_id' => $sparse->id, 'embedding_space_generation_id' => $space->id]);
        $g = WorkspaceCorpusGeneration::factory()->active()->create(['workspace_id' => $workspace->id, 'embedding_space_generation_id' => $space->id, 'sparse_space_generation_id' => $ss->id, 'verified_at' => now(), 'expected_point_count' => 0, 'point_manifest_digest' => hash('sha256', 'empty synthetic corpus')]);
        $workspace->update(['active_workspace_corpus_generation_id' => $g->id]);
        config(['conversation.current_policy_workspace_ids' => [$workspace->public_id]]);
        $pin = app(GenerationProfileFactory::class)->needsPin($workspace->public_id);
        $response = json_decode(file_get_contents('/contracts/http/current-policy-needs/v1/response.example.json'), true, flags: JSON_THROW_ON_ERROR);
        $encoded = str_replace('"u1"', '"user-1"', json_encode($response, JSON_THROW_ON_ERROR));
        $response = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        $question = $response['plan']['needs'][0]['question_span']['quote'];
        Queue::fake();
        Http::preventStrayRequests();
        $ids = [];
        Http::fake(function ($request) use ($response, $question, &$ids) {
            $this->assertStringEndsWith('/api/internal/retrieval/plan-needs', $request->url());
            $body = $request->data();
            $ids[] = $body['request_id'];
            $this->assertSame(3, $body['contract_version']);
            $this->assertSame('current-policy-pool-v1', $body['product_profile']);
            $this->assertSame([['turn_id' => 'user-1', 'text' => $question]], $body['user_turns']);
            $signature = app(RetrievalCallSigner::class)->headers('POST', '/api/internal/retrieval/plan-needs', $request->body(), $body['workspace_id'], 'retrieval.plan-needs', $body['request_id'], (int) $request->header('X-Retrieval-Caller-Timestamp')[0]);
            $this->assertSame($signature['X-Retrieval-Caller-Signature'], $request->header('X-Retrieval-Caller-Signature')[0]);

            return Http::response([...$response, 'request_id' => $body['request_id']]);
        });
        for ($i = 0; $i < 2; $i++) {
            $record = \plannerScopeDiagnostic(['invocation_id' => (string) Str::uuid(), 'utterance' => $question, 'expected_profile' => $pin]);
            $this->assertSame('accepted', $record['status']);
            $this->assertNotEmpty($record['requests']);
            $this->assertTrue($record['requests'][0]['signed']);
            $this->assertSame('Europe/London', $record['timezone']);
        }
        $this->assertCount(2, array_unique($ids));
        Http::assertSentCount(2);
        Queue::assertNothingPushed();
        foreach (['conversations', 'messages', 'generation_runs', 'generated_answers', 'workspace_usage_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
