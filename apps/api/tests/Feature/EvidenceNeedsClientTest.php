<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\RetrievalPlannerException;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EvidenceNeedsClientTest extends TestCase
{
    use RefreshDatabase;

    private function example(): array
    {
        return json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v2/examples.json'), true, flags: JSON_THROW_ON_ERROR)[0];
    }

    private function workspace(): Workspace
    {
        return (new Workspace)->forceFill(['public_id' => (string) Str::uuid()]);
    }

    private function response(Request $request): array
    {
        $lineage = ['provider' => 'openai', 'model' => 'gpt-5-mini',
            'contract_schema_version' => 'evidence-needs-plan-v2',
            'prompt_version' => 'bounded-evidence-needs-v12', 'adapter_version' => 'structured-chat-needs-v5'];
        ksort($lineage);
        $lineage['fingerprint'] = hash('sha256', json_encode($lineage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return ['contract_version' => 2, 'request_id' => $request['request_id'],
            'plan' => $this->example()['plan'], 'classifier_lineage' => $lineage,
            'usage' => ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini',
                'execution' => 'provider_api', 'request_count' => 1, 'retry_count' => 0,
                'input_tokens' => 120, 'cached_input_tokens' => null, 'output_tokens' => 90,
                'latency_ms' => 10, 'cost_basis' => 'unavailable', 'cost_usd' => null, 'pricing_snapshot' => null]];
    }

    public function test_real_consumer_sends_signed_needs_contract_and_checks_both_schemas(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $this->assertStringEndsWith('/api/internal/retrieval/plan-needs', $request->url());
            $this->assertSame(['retrieval.plan-needs'], $request->header('X-Retrieval-Caller-Purpose'));
            $this->assertSame($this->example()['turns'], $request['user_turns']);
            $this->assertArrayNotHasKey('expected', $request->data());
            $response = $this->response($request);
            foreach (['request' => $request->data(), 'response' => $response] as $name => $payload) {
                $schema = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v2/'.$name.'.schema.json'), flags: JSON_THROW_ON_ERROR);
                $this->assertTrue((new Validator)->validate(json_decode(json_encode($payload, JSON_THROW_ON_ERROR)), $schema)->isValid());
            }

            return Http::response($response);
        });
        $plan = app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
        $this->assertSame($this->example()['plan'], $plan->value);
        Http::assertSentCount(1);
    }

    public function test_ungrounded_response_is_not_retried_or_sent_to_legacy_route(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $response = $this->response($request);
            $response['plan']['needs'][0]['question_span']['quote'] = 'not in this conversation';

            return Http::response($response);
        });
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Ungrounded plan was accepted.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame('invalid_grounded_needs_plan', $exception->category);
        }
        Http::assertSentCount(1);
    }

    public function test_unknown_catalogue_identity_is_rejected_before_retrieval(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $response = $this->response($request);
            $response['plan']['bindings'][0]['subject_status'] = 'matched';
            $response['plan']['bindings'][0]['family_ids'] = [(string) Str::uuid()];

            return Http::response($response);
        });
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Unknown catalogue identity was accepted.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame('invalid_grounded_needs_plan', $exception->category);
        }
        Http::assertSentCount(1);
    }

    public function test_missing_binding_is_rejected_before_retrieval(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $response = $this->response($request);
            $response['plan']['bindings'] = [];

            return Http::response($response);
        });
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Incomplete catalogue binding was accepted.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame('invalid_grounded_needs_plan', $exception->category);
        }
        Http::assertSentCount(1);
    }

    public function test_typed_needs_failure_preserves_status_and_does_not_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake(Http::response(['detail' => ['code' => 'retrieval_planning_failed',
            'category' => 'invalid_grounded_needs_plan', 'provider_status' => 200,
            'attempt_count' => 1, 'systemic' => false]], 503));
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Expected typed planning failure.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame('invalid_grounded_needs_plan', $exception->category);
            $this->assertSame(200, $exception->providerStatus);
        }
        Http::assertSentCount(1);
    }

    public static function diagnosticFields(): array
    {
        return [['bindings.0.family_ids'], ['PRIVATE-CONTENT-MUST-NOT-LEAK']];
    }

    public function test_completion_failure_metadata_is_allowlisted(): void
    {
        Http::preventStrayRequests();
        Http::fake(Http::response(['detail' => ['category' => 'invalid_grounded_needs_plan',
            'provider_status' => 200, 'attempt_count' => 1, 'systemic' => false,
            'validation' => ['stage' => 'completion', 'field' => 'root', 'code' => 'output_truncated'],
            'completion' => ['finish_reason' => 'length', 'input_tokens' => 100,
                'output_tokens' => 4096, 'reasoning_tokens' => 4000, 'content' => 'PRIVATE']]], 503));
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Expected completion failure.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame(['finish_reason' => 'length', 'input_tokens' => 100,
                'output_tokens' => 4096, 'reasoning_tokens' => 4000], $exception->completion);
            $this->assertSame('output_truncated', $exception->validation['code']);
        }
        Http::assertSentCount(1);
    }

    #[DataProvider('diagnosticFields')]
    public function test_safe_validation_detail_survives_without_arbitrary_content(string $field): void
    {
        Http::preventStrayRequests();
        Http::fake(Http::response(['detail' => ['category' => 'invalid_grounded_needs_plan',
            'provider_status' => 200, 'attempt_count' => 1, 'systemic' => false,
            'validation' => ['stage' => 'schema', 'field' => $field, 'code' => 'uuid_parsing',
                'input' => 'PRIVATE-CONTENT-MUST-NOT-LEAK']]], 503));
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Expected validation failure.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame($field === 'bindings.0.family_ids' ?
                ['stage' => 'schema', 'field' => $field, 'code' => 'uuid_parsing'] : null, $exception->validation);
        }
    }
}
