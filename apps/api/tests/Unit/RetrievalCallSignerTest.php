<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Retrieval\RetrievalCallSigner;
use Tests\TestCase;

class RetrievalCallSignerTest extends TestCase
{
    public function test_it_matches_adr_0018_normative_vector(): void
    {
        config()->set('retrieval.caller.key_id', 'test-key');
        config()->set(
            'retrieval.caller.secret',
            'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
        );

        $headers = app(RetrievalCallSigner::class)->headers(
            'POST',
            '/api/internal/retrieval/search',
            '{"contract_version":1,"workspace_id":"5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41","embedding_space_generation_id":"7c1a2b3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d"}',
            '5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41',
            'retrieval.search',
            'b6e4a1d2-9f3c-4b7a-8e2d-5c1f0a9b8d7e',
            1785326400,
        );

        $this->assertSame(
            'rc1=f2329579bdd74d6871d52c52b06095a7ce2fdb42d57ffdb9fe541990040195e8',
            $headers['X-Retrieval-Caller-Signature'],
        );
        $this->assertSame('retrieval.search', $headers['X-Retrieval-Caller-Purpose']);
    }
}
