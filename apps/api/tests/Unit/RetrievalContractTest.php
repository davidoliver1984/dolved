<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Str;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetrievalContractTest extends TestCase
{
    public function test_laravel_request_shapes_match_both_shared_rc1_schemas(): void
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
        $this->assertMatchesSchema('search-v1.schema.json', $search);
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
