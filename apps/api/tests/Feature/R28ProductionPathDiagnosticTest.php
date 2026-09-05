<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Evaluation\RunR28ProductionPathDiagnostic;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class R28ProductionPathDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_only_subset_runs_through_the_production_conversation_orchestrator(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($workspace)->for($user)->create([
            'role' => WorkspaceRole::Owner,
        ]);
        $requests = [];
        Http::fake(function (Request $request) use (&$requests) {
            $requests[] = $request->data();
            $body = $request->data();

            if (str_ends_with($request->url(), '/plan')) {
                return Http::response($this->planResponse($body));
            }

            return Http::response([
                'contract_version' => 1,
                'request_id' => $body['request_id'],
                'result' => [
                    'status' => 'resolved',
                    'resolved_query' => $body['current_message'],
                    'used_prior_context' => false,
                    'interpretation_metadata' => ['used_turn_ordinals' => []],
                    'clarification_question' => null,
                    'contextualiser_version' => 'recording-contextualiser-v1',
                    'usage' => ['execution' => 'recording', 'request_count' => 0],
                ],
            ]);
        });
        $items = collect(range(1, 12))->map(fn (int $index): array => [
            'case_id' => sprintf('case-%02d', $index),
            'variant_id' => 'v1',
            'utterance' => sprintf('Independent diagnostic question %02d?', $index),
        ])->all();

        $result = app(RunR28ProductionPathDiagnostic::class)->handle(
            $user,
            (string) $workspace->public_id,
            [
                'schema_version' => 'r28-production-path-input-v1',
                'subset_id' => 'r28-production-path-diagnostic-12-v1',
                'population_id' => 'dolved-care-v4-evaluation-population-v2',
                'population_digest' => str_repeat('a', 64),
                'items' => $items,
            ],
        );

        $this->assertSame(OrchestrateConversationRun::class, $result['execution_boundary']);
        $this->assertCount(12, $result['items']);
        $this->assertCount(24, $requests);
        $this->assertSame(array_column($items, 'case_id'), array_column($result['items'], 'case_id'));
        $this->assertSame(
            array_fill(0, 12, 'clarification_required'),
            array_column($result['items'], 'status'),
            json_encode($result['items'][0], JSON_THROW_ON_ERROR),
        );
        $this->assertDatabaseCount('conversations', 12);
        $this->assertDatabaseCount('generation_runs', 12);
        $this->assertDatabaseCount('contextualisation_result_snapshots', 12);
        $this->assertDatabaseCount('retrieval_outcome_snapshots', 12);
        $this->assertDatabaseCount('generated_answers', 0);
        $this->assertSame(
            'clarification_required',
            $result['items'][0]['production_trace']['plan']['temporal_mode'],
        );
        $this->assertSame(
            'clarification_required',
            $result['items'][0]['production_trace']['eligibility']['outcome'],
        );
        $this->assertStringNotContainsString(
            'expected',
            json_encode($result, JSON_THROW_ON_ERROR),
        );
    }

    public function test_execution_input_rejects_scoring_truth(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($workspace)->for($user)->create([
            'role' => WorkspaceRole::Owner,
        ]);
        $items = collect(range(1, 12))->map(fn (int $index): array => [
            'case_id' => sprintf('case-%02d', $index),
            'variant_id' => 'v1',
            'utterance' => sprintf('Question %02d?', $index),
        ])->all();
        $items[0]['expected_outcome'] = 'evidence_found';

        $this->expectExceptionMessage('only question identities and utterances');
        app(RunR28ProductionPathDiagnostic::class)->handle(
            $user,
            (string) $workspace->public_id,
            [
                'schema_version' => 'r28-production-path-input-v1',
                'subset_id' => 'r28-production-path-diagnostic-12-v1',
                'population_id' => 'dolved-care-v4-evaluation-population-v2',
                'population_digest' => str_repeat('a', 64),
                'items' => $items,
            ],
        );
    }

    public function test_diagnostic_boundary_delegates_instead_of_reimplementing_retrieval(): void
    {
        $source = file_get_contents(app_path('Actions/Evaluation/RunR28ProductionPathDiagnostic.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('OrchestrateConversationRun', $source);
        foreach ([
            'eligible_chunk_indices',
            'side_chunk_indices',
            'derive_deterministic_outcome',
            'ReciprocalRankFusion',
            'cosine',
        ] as $parallelImplementation) {
            $this->assertStringNotContainsString($parallelImplementation, $source);
        }
    }

    public function test_command_writes_bounded_resumable_case_records_and_routes_injection_scope(): void
    {
        $primary = Workspace::factory()->create();
        $primaryUser = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($primary)->for($primaryUser)->create(['role' => WorkspaceRole::Owner]);
        $injection = Workspace::factory()->create();
        $injectionUser = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($injection)->for($injectionUser)->create(['role' => WorkspaceRole::Owner]);
        Http::fake(function (Request $request) {
            $body = $request->data();

            return Http::response(str_ends_with($request->url(), '/plan')
                ? $this->planResponse($body)
                : [
                    'contract_version' => 1,
                    'request_id' => $body['request_id'],
                    'result' => [
                        'status' => 'resolved', 'resolved_query' => $body['current_message'],
                        'used_prior_context' => false, 'interpretation_metadata' => ['used_turn_ordinals' => []],
                        'clarification_question' => null, 'contextualiser_version' => 'recording-contextualiser-v1',
                        'usage' => ['execution' => 'recording', 'request_count' => 0],
                    ],
                ]);
        });
        $items = collect(range(1, 12))->map(fn (int $index): array => [
            'case_id' => sprintf('case-%02d', $index),
            'variant_id' => 'v1',
            'utterance' => sprintf('Question %02d?', $index),
        ])->all();
        $directory = sys_get_temp_dir().'/r28-report-'.Str::uuid();
        mkdir($directory, 0700);
        $inputPath = $directory.'/input.json';
        $selectionPath = $directory.'/selection.json';
        $outputPath = $directory.'/observations.json';
        file_put_contents($inputPath, json_encode([
            'schema_version' => 'r28-production-path-input-v1',
            'subset_id' => 'r28-production-path-diagnostic-12-v1',
            'population_id' => 'dolved-care-v4-evaluation-population-v2',
            'population_digest' => str_repeat('a', 64),
            'items' => $items,
        ], JSON_THROW_ON_ERROR));
        file_put_contents($selectionPath, json_encode([
            'selection' => [
                'primary' => collect(range(1, 10))->map(fn (int $index): string => sprintf('case-%02d::v1', $index))->all(),
                'prompt_injection' => ['case-11::v1', 'case-12::v1'],
            ],
        ], JSON_THROW_ON_ERROR));

        $exit = Artisan::call('evaluation:r28:production-path', [
            '--input' => $inputPath,
            '--selection' => $selectionPath,
            '--output' => $outputPath,
            '--workspace' => $primary->public_id,
            '--user' => $primaryUser->id,
            '--injection-workspace' => $injection->public_id,
            '--injection-user' => $injectionUser->id,
        ]);
        $this->assertSame(0, $exit, Artisan::output());

        $aggregate = json_decode((string) file_get_contents($outputPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertLessThan(1_000_000, filesize($outputPath));
        $this->assertCount(12, $aggregate['items']);
        $this->assertSame(array_fill(0, 10, 'primary'), array_column(array_slice($aggregate['items'], 0, 10), 'scope_classification'));
        $this->assertSame(['injection', 'injection'], array_column(array_slice($aggregate['items'], 10), 'scope_classification'));
        $this->assertSame([(string) $injection->public_id, (string) $injection->public_id], array_column(array_slice($aggregate['items'], 10), 'workspace_id'));
        $this->assertCount(12, glob($outputPath.'.cases/*.json') ?: []);
        $this->assertLessThan(10_000_000, array_sum(array_column($aggregate['items'], 'bytes')));
        $this->assertStringNotContainsString('chunk_text', (string) file_get_contents($outputPath.'.cases/case-01__v1.json'));

        $before = collect($aggregate['items'])->pluck('sha256', 'case_record')->all();
        $conversationCount = $primary->conversations()->count() + $injection->conversations()->count();
        unlink($outputPath);
        $exit = Artisan::call('evaluation:r28:production-path', [
            '--input' => $inputPath,
            '--selection' => $selectionPath,
            '--output' => $outputPath,
            '--workspace' => $primary->public_id,
            '--user' => $primaryUser->id,
            '--injection-workspace' => $injection->public_id,
            '--injection-user' => $injectionUser->id,
        ]);
        $this->assertSame(0, $exit, Artisan::output());
        $resumed = json_decode((string) file_get_contents($outputPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($before, collect($resumed['items'])->pluck('sha256', 'case_record')->all());
        $this->assertSame($conversationCount, $primary->conversations()->count() + $injection->conversations()->count());
    }

    public function test_unseen_confirmation_manifest_routes_all_five_cases_only_to_the_primary_workspace(): void
    {
        $primary = Workspace::factory()->create();
        $primaryUser = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMembership::factory()->for($primary)->for($primaryUser)->create(['role' => WorkspaceRole::Owner]);
        Http::fake(function (Request $request) {
            $body = $request->data();

            return Http::response(str_ends_with($request->url(), '/plan')
                ? $this->planResponse($body)
                : [
                    'contract_version' => 1,
                    'request_id' => $body['request_id'],
                    'result' => [
                        'status' => 'resolved', 'resolved_query' => $body['current_message'],
                        'used_prior_context' => false, 'interpretation_metadata' => ['used_turn_ordinals' => []],
                        'clarification_question' => null, 'contextualiser_version' => 'recording-contextualiser-v1',
                        'usage' => ['execution' => 'recording', 'request_count' => 0],
                    ],
                ]);
        });
        $categories = [
            'temporal_comparison',
            'historical_or_valid_at',
            'competing_or_near_duplicate',
            'ordinary_current_evidence_found',
            'cross_tenant_safety',
        ];
        $items = collect($categories)->map(fn (string $category, int $index): array => [
            'case_id' => sprintf('synthetic-case-%02d', $index + 1),
            'variant_id' => 'v1',
            'utterance' => sprintf('Synthetic independent question %02d?', $index + 1),
        ])->all();
        $directory = sys_get_temp_dir().'/r28-unseen-'.Str::uuid();
        mkdir($directory, 0700);
        $inputPath = $directory.'/input.json';
        $selectionPath = $directory.'/selection.json';
        $outputPath = $directory.'/observations.json';
        file_put_contents($inputPath, json_encode([
            'schema_version' => 'r28-production-path-input-v1',
            'subset_id' => 'synthetic-unseen-five',
            'population_id' => 'synthetic-population',
            'population_digest' => str_repeat('b', 64),
            'items' => $items,
        ], JSON_THROW_ON_ERROR));
        file_put_contents($selectionPath, json_encode([
            'schema_version' => 'r28-unseen-confirmation-selection-v1',
            'items' => collect($items)->map(fn (array $item, int $index): array => [
                'category' => $categories[$index],
                'case_id' => $item['case_id'],
                'variant_id' => $item['variant_id'],
            ])->all(),
        ], JSON_THROW_ON_ERROR));

        $exit = Artisan::call('evaluation:r28:production-path', [
            '--input' => $inputPath,
            '--selection' => $selectionPath,
            '--output' => $outputPath,
            '--workspace' => $primary->public_id,
            '--user' => $primaryUser->id,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        $aggregate = json_decode((string) file_get_contents($outputPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(5, $aggregate['items']);
        $this->assertSame(array_fill(0, 5, 'primary'), array_column($aggregate['items'], 'scope_classification'));
        $this->assertSame(array_fill(0, 5, (string) $primary->public_id), array_column($aggregate['items'], 'workspace_id'));
    }

    /** @param array<string, mixed> $body */
    private function planResponse(array $body): array
    {
        $lineage = [
            'provider' => 'deterministic',
            'model' => 'fixed-retrieval-planner',
            'contract_schema_version' => 'plan-response-v2',
            'prompt_version' => 'fixed-v1',
            'adapter_version' => 'fixed-v1',
        ];
        $canonical = $lineage;
        ksort($canonical);

        return [
            'contract_version' => 2,
            'request_id' => $body['request_id'],
            'plan' => [
                'retrieval_queries' => [$body['question']],
                'temporal_mode' => 'clarification_required',
                'explicit_date' => null,
                'temporal_reference' => null,
                'location_references' => [],
                'clarification_reason' => 'unclassifiable_temporal_intent',
            ],
            'classifier_lineage' => $lineage + [
                'fingerprint' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ],
            'usage' => [
                'stage' => 'planner',
                'provider' => 'deterministic',
                'model' => 'fixed-retrieval-planner',
                'execution' => 'local',
                'request_count' => 1,
                'retry_count' => 0,
                'input_tokens' => null,
                'cached_input_tokens' => null,
                'output_tokens' => null,
                'latency_ms' => 0,
                'cost_basis' => 'zero_cost_local',
                'cost_usd' => 0,
                'pricing_snapshot' => null,
            ],
        ];
    }
}
