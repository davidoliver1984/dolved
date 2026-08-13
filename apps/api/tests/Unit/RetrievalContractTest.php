<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Str;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetrievalContractTest extends TestCase
{
    public function test_laravel_request_shapes_match_shared_rc1_schemas(): void
    {
        $workspaceId = (string) Str::uuid();
        $plan = [
            'contract_version' => 1,
            'request_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'question' => 'What is current?',
            'evaluated_at' => '2026-08-07T12:00:00+00:00',
        ];
        $search = [
            'contract_version' => 1,
            'request_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'query' => 'What is current?',
            'embedding_profile' => ['provider' => 'voyage'],
            'embedding_profile_fingerprint' => str_repeat('a', 64),
            'vector_space' => ['collection_name' => 'rag-platform-vectors-v1'],
            'workspace_corpus_generation_id' => (string) Str::uuid(),
            'candidate_k' => 10,
            'scopes' => [[
                'side' => 'primary',
                'eligible_document_ids' => [(string) Str::uuid()],
            ]],
        ];

        $this->assertMatchesSchema('plan-v1.schema.json', $plan);
        $planResponse = [
            'contract_version' => 2,
            'request_id' => $plan['request_id'],
            'plan' => [
                'retrieval_queries' => [$plan['question']],
                'temporal_mode' => 'current',
                'explicit_date' => null,
                'temporal_reference' => null,
                'location_references' => [],
                'clarification_reason' => null,
            ],
            'classifier_lineage' => [
                'provider' => 'deterministic',
                'model' => 'fixed',
                'contract_schema_version' => 'plan-response-v2',
                'prompt_version' => 'fixed-v1',
                'adapter_version' => 'fixed-v1',
                'fingerprint' => str_repeat('a', 64),
            ],
            'usage' => [
                'stage' => 'planner',
                'provider' => 'deterministic',
                'model' => 'fixed',
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
        $this->assertMatchesSchema('plan-response-v2.schema.json', $planResponse);
        $this->assertMatchesSchema('plan-response-v2.schema.json', array_replace_recursive(
            $planResponse,
            ['usage' => [
                'provider_attempt_count' => 2,
                'provider_retry_count' => 1,
                'outer_attempt_count' => 1,
                'outer_retry_count' => 0,
                'rate_limit_event_count' => 1,
                'retry_delays' => [[
                    'delay_seconds' => 15,
                    'source' => 'configured_fallback',
                ]],
                'first_provider_attempt_at' => '2026-08-12T12:00:00+00:00',
                'final_provider_success_at' => '2026-08-12T12:00:15+00:00',
                'provider_retry_elapsed_ms' => 15000,
            ]],
        ));
        $this->assertMatchesSchema('search-v1.schema.json', $search);
        $rerank = [
            'contract_version' => 1,
            'request_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'query' => 'What is current?',
            'profile' => [
                'provider' => 'voyage',
                'model' => 'rerank-2.5',
                'adapter_version' => '1',
                'truncation' => false,
            ],
            'candidates' => [[
                'chunk_id' => (string) Str::uuid(),
                'document_id' => (string) Str::uuid(),
                'document_family_id' => (string) Str::uuid(),
                'version_position' => 1,
                'side' => 'primary',
                'text' => 'Canonical policy text.',
                'fused_score' => 0.04,
                'fused_rank' => 1,
            ]],
            'top_k' => 1,
        ];
        $this->assertMatchesSchema('rerank-v1.schema.json', $rerank);

        $rebuild = [
            'contract_version' => 1,
            'request_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'rebuild_event_id' => (string) Str::uuid(),
            'embedding_profile' => ['provider' => 'voyage'],
            'sparse_embedding_profile' => ['provider' => 'fastembed'],
            'vector_space' => ['collection_name' => 'rag-platform-vectors-v1'],
            'workspace_corpus_generation_id' => (string) Str::uuid(),
            'chunks' => [[
                'chunk_id' => (string) Str::uuid(),
                'document_id' => (string) Str::uuid(),
                'text' => 'Canonical policy text.',
            ]],
        ];
        $this->assertMatchesSchema('corpus-rebuild-batch-v1.schema.json', $rebuild);
        $this->assertMatchesSchema('corpus-verify-v1.schema.json', [
            'contract_version' => 1,
            'request_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'vector_space' => ['collection_name' => 'rag-platform-vectors-v1'],
            'workspace_corpus_generation_id' => (string) Str::uuid(),
            'points' => [[
                'chunk_id' => (string) Str::uuid(),
                'document_id' => (string) Str::uuid(),
                'event_id' => (string) Str::uuid(),
            ]],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function assertMatchesSchema(string $name, array $payload): void
    {
        $contents = file_get_contents('/contracts/http/retrieval-call/rc1/'.$name);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the shared retrieval contract.');
        }
        $schema = json_decode($contents, flags: JSON_THROW_ON_ERROR);
        $object = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        $result = (new Validator)->validate($object, $schema);

        $this->assertTrue($result->isValid(), $result->error()?->message() ?? 'Contract validation failed.');
    }
}
