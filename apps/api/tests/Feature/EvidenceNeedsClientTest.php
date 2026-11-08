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
        return json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/examples.json'), true, flags: JSON_THROW_ON_ERROR)[0];
    }

    private function workspace(): Workspace
    {
        return (new Workspace)->forceFill(['public_id' => (string) Str::uuid()]);
    }

    private function response(Request $request): array
    {
        $lineage = ['provider' => 'openai', 'model' => 'gpt-5-mini',
            'contract_schema_version' => 'evidence-needs-plan-v3',
            'prompt_version' => 'bounded-evidence-needs-v20', 'adapter_version' => 'structured-chat-needs-v7'];
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
                $schema = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/'.$name.'.schema.json'), flags: JSON_THROW_ON_ERROR);
                $this->assertTrue((new Validator)->validate(json_decode(json_encode($payload, JSON_THROW_ON_ERROR)), $schema)->isValid());
            }

            return Http::response($response);
        });
        $plan = app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
        $this->assertSame($this->example()['plan'], $plan->value);
        Http::assertSentCount(1);
    }

    public function test_m07_shaped_normalized_claim_plan_passes_real_laravel_consumer(): void
    {
        $question = 'Do formal grievances go to the Brambleford Office People team?';
        $turns = [['turn_id' => 'u1', 'text' => $question]];
        $claim = ['version' => 'claim-under-test-v1', 'type' => 'named_recipient',
            'relation' => 'submission_destination',
            'source' => ['turn_id' => 'u1', 'quote' => 'Brambleford Office People team']];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($question, $turns, $claim) {
            $this->assertSame($turns, $request['user_turns']);
            $response = $this->response($request);
            $need = $response['plan']['needs'][0];
            $need['aspect'] = $question;
            $need['question_span'] = ['turn_id' => 'u1', 'quote' => $question];
            $need['operation'] = 'retrieve';
            $need['authority'] = ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null];
            $need['fact_reference'] = null;
            $need['applicability'] = ['kind' => 'generic', 'source' => null];
            $need['claim_under_test'] = $claim;
            $need['scope_question'] = null;
            $need['supports_need_id'] = null;
            $response['plan']['needs'] = [$need];
            $response['plan']['bindings'] = [$response['plan']['bindings'][0]];

            return Http::response($response);
        });
        $plan = app(RetrievalClient::class)->planNeeds($this->workspace(), $turns, CarbonImmutable::now());
        $this->assertSame('retrieve', $plan->value['needs'][0]['operation']);
        $this->assertNull($plan->value['needs'][0]['scope_question']);
        $this->assertSame($claim, $plan->value['needs'][0]['claim_under_test']);
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
            $this->assertSame('Reference is not grounded in an authorised user turn.', $exception->validation['message']);
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
            $this->assertSame('Invalid authorised catalogue binding.', $exception->validation['message']);
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
            $this->assertSame('Every need requires one catalogue binding.', $exception->validation['message']);
        }
        Http::assertSentCount(1);
    }

    public function test_wire_shape_failure_captures_only_static_safe_message(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $response = $this->response($request);
            $response['plan']['needs'][0]['aspect'] = ['PRIVATE-CONTENT-MUST-NOT-LEAK'];

            return Http::response($response);
        });
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Invalid wire shape was accepted.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame(['stage' => 'application_contract', 'field' => 'root',
                'code' => 'invalid_plan', 'message' => 'Invalid evidence needs shape.'], $exception->validation);
            $this->assertStringNotContainsString('PRIVATE-CONTENT-MUST-NOT-LEAK', json_encode($exception->validation));
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

    public static function needsDiagnosticCases(): array
    {
        return [
            'scope question required' => ['needs.0', 'scope_question_required'],
            'scope question misplaced' => ['needs.0', 'scope_question_misplaced'],
            'scope claim conflict' => ['needs.0', 'scope_claim_conflict'],
            'missing claim relation' => ['needs.0.claim_under_test.relation', 'missing'],
            'missing top-level needs' => ['needs', 'missing'],
            'missing aspect' => ['needs.0.aspect', 'missing'],
        ];
    }

    #[DataProvider('needsDiagnosticCases')]
    public function test_safe_needs_diagnostic_survives_typed_failure_without_input(string $field, string $code): void
    {
        Http::preventStrayRequests();
        Http::fake(Http::response(['detail' => ['category' => 'invalid_grounded_needs_plan',
            'provider_status' => 200, 'attempt_count' => 1, 'systemic' => false,
            'validation' => ['stage' => 'schema', 'field' => $field, 'code' => $code,
                'input' => 'PRIVATE-CONTENT-MUST-NOT-LEAK']]], 503));
        try {
            app(RetrievalClient::class)->planNeeds($this->workspace(), $this->example()['turns'], CarbonImmutable::now());
            $this->fail('Expected typed validation failure.');
        } catch (RetrievalPlannerException $exception) {
            $this->assertSame(['stage' => 'schema', 'field' => $field, 'code' => $code], $exception->validation);
        }
        Http::assertSentCount(1);
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
