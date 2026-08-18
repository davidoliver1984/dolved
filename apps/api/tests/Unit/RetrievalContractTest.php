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
        $contextualise = [
            'contract_version' => 1,
            'request_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'current_message' => 'What about that procedure?',
            'history' => [[
                'user_message' => 'Which procedure applies?',
                'assistant_message' => 'The current procedure applies.',
                'assistant_kind' => 'grounded_answer',
                'user_ordinal' => 1,
                'assistant_ordinal' => 2,
            ]],
            'context_policy_version' => 'bounded-completed-turns-v1',
        ];
        $this->assertMatchesSchema('conversation-contextualize-v1.schema.json', $contextualise);
        $this->assertMatchesSchema('conversation-contextualize-response-v1.schema.json', [
            'contract_version' => 1,
            'request_id' => $contextualise['request_id'],
            'result' => [
                'status' => 'resolved',
                'resolved_query' => 'Which current procedure applies?',
                'used_prior_context' => true,
                'interpretation_metadata' => ['used_turn_ordinals' => [1]],
                'clarification_question' => null,
                'contextualiser_version' => 'conversation-context-v1',
                'usage' => ['execution' => 'provider_api', 'request_count' => 1],
            ],
        ]);
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

        $generationRequest = [
            'contract_version' => 1,
            'request_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'question' => 'What is the current procedure?',
            'evidence' => [[
                'evidence_id' => 'ev-01',
                'text' => 'Canonical evidence.',
                'document_chunk_id' => 1,
                'document_id' => 2,
                'ingestion_event_claim_id' => 3,
                'source_provenance' => [['kind' => 'text']],
                'temporal_authority' => ['mode' => 'current'],
                'applicability_context' => ['scope' => 'universal'],
                'side' => 'primary',
            ]],
            'constraints' => [
                'context_policy_version' => 'whole-evidence-v1',
                'max_context_characters' => 60000,
                'required_sides' => ['primary'],
            ],
        ];
        $this->assertMatchesSchema('generation-answer-v1.schema.json', $generationRequest);
        $this->assertMatchesSchema('generation-answer-response-v1.schema.json', [
            'contract_version' => 1,
            'request_id' => $generationRequest['request_id'],
            'status' => 'completed',
            'result' => [
                'contract_version' => 1,
                'outcome' => 'answered',
                'answer_parts' => [['text' => 'Grounded answer.', 'evidence_ids' => ['ev-01']]],
                'unsupported_aspects' => [],
                'insufficiency_reason' => null,
                'usage' => null,
            ],
        ]);
        $streamBase = [
            'contract_version' => 1, 'request_id' => $generationRequest['request_id'],
            'candidate' => null, 'result' => null, 'error' => null, 'failure' => null,
        ];
        $this->assertMatchesSchema('generation-stream-event-v1.schema.json', array_replace($streamBase, [
            'sequence' => 1, 'event_type' => 'answer_part_candidate',
            'candidate' => ['text' => 'Grounded answer.', 'evidence_ids' => ['ev-01']],
        ]));
        $this->assertMatchesSchema('generation-stream-event-v1.schema.json', array_replace($streamBase, [
            'sequence' => 2, 'event_type' => 'generation_completed',
            'result' => [
                'contract_version' => 1, 'outcome' => 'answered',
                'answer_parts' => [['text' => 'Grounded answer.', 'evidence_ids' => ['ev-01']]],
                'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
            ],
        ]));
        $this->assertMatchesSchema('generation-answer-response-v1.schema.json', [
            'contract_version' => 1,
            'request_id' => $generationRequest['request_id'],
            'status' => 'context_budget_exceeded',
            'failure' => [
                'code' => 'GENERATION_CONTEXT_BUDGET_EXCEEDED',
                'policy_version' => 'whole-evidence-v1',
                'proposed_units' => 60001,
                'maximum_units' => 60000,
            ],
        ]);
        $this->assertMatchesSchema('generation-answer-response-v1.schema.json', [
            'contract_version' => 1,
            'request_id' => $generationRequest['request_id'],
            'status' => 'provider_error',
            'error' => [
                'category' => 'timeout', 'provider' => null, 'model' => null,
                'http_status' => null, 'attempt_count' => 1, 'latency_ms' => 10,
            ],
        ]);
    }

    public function test_generation_response_schema_rejects_mixed_missing_unknown_and_smuggled_shapes(): void
    {
        $requestId = (string) Str::uuid();
        $result = [
            'contract_version' => 1,
            'outcome' => 'answered',
            'answer_parts' => [['text' => 'Grounded answer.', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => [],
            'insufficiency_reason' => null,
            'usage' => null,
        ];
        $failure = [
            'code' => 'GENERATION_CONTEXT_BUDGET_EXCEEDED',
            'policy_version' => 'whole-evidence-v1',
            'proposed_units' => 101,
            'maximum_units' => 100,
        ];
        $error = [
            'category' => 'timeout', 'provider' => null, 'model' => null,
            'http_status' => null, 'attempt_count' => 1, 'latency_ms' => 10,
        ];
        $base = ['contract_version' => 1, 'request_id' => $requestId];

        foreach ([
            $base + ['status' => 'completed', 'result' => $result, 'error' => $error],
            $base + ['status' => 'completed', 'result' => $result, 'failure' => $failure],
            $base + ['status' => 'context_budget_exceeded', 'failure' => $failure, 'error' => $error],
            $base + ['status' => 'unknown_fourth_shape', 'result' => $result],
            $base + ['result' => $result],
            $base + ['status' => 'completed', 'result' => $result, 'smuggled' => []],
        ] as $invalid) {
            $this->assertDoesNotMatchSchema('generation-answer-response-v1.schema.json', $invalid);
        }
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

    /** @param array<string, mixed> $payload */
    private function assertDoesNotMatchSchema(string $name, array $payload): void
    {
        $contents = file_get_contents('/contracts/http/retrieval-call/rc1/'.$name);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the shared retrieval contract.');
        }
        $schema = json_decode($contents, flags: JSON_THROW_ON_ERROR);
        $object = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

        $this->assertFalse((new Validator)->validate($object, $schema)->isValid());
    }
}
