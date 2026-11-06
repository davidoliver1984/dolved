<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Generation\GenerateGroundedAnswer;
use App\Actions\Generation\PersistGeneratedAnswer;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\GenerationDeliveryMode;
use App\Enums\GenerationOutcome;
use App\Enums\GenerationSide;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationContextBudgetExceeded;
use App\Exceptions\GenerationException;
use App\Exceptions\GenerationProviderException;
use App\Exceptions\GenerationStreamException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Generation\AssembleGenerationRequest;
use App\Services\Generation\GenerationClient;
use App\Services\Generation\GenerationContextPacker;
use App\Services\Generation\GenerationFingerprint;
use App\Services\Generation\GenerationProfileFactory;
use App\Services\Generation\ValidateGenerationResult;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationEvidence;
use App\Support\Generation\GenerationProfile;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\GenerationResult;
use App\Support\Generation\GenerationValidationDiagnostics;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\RetrievalResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroundedGenerationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_needs_evidence_keeps_authored_banner_and_adds_resolved_current_status(): void
    {
        [$base, $chunk] = $this->assemblyFixture();
        $chunk->document->forceFill([
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => CarbonImmutable::parse('2026-08-31T23:59:59+00:00'),
            'effective_from' => CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
        ])->save();
        $banner = 'Not yet effective; becomes authoritative on 1 September 2026.';
        $bannerChunk = DocumentChunk::factory()->create([
            'workspace_id' => $chunk->workspace_id,
            'document_id' => $chunk->document_id,
            'ingestion_event_claim_id' => $chunk->ingestion_event_claim_id,
            'ordinal' => 2,
            'text' => $banner,
            'content_digest' => hash('sha256', $banner),
        ]);
        $examples = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/examples.json'), true, flags: JSON_THROW_ON_ERROR);
        $need = $examples[0]['plan']['needs'][1];
        $need['need_id'] = 'n1';
        unset($need['supports_need_id']);
        $need += ['retrieval_outcome' => 'EVIDENCE_FOUND', 'retrieval_reason' => null,
            'resolved_temporal_authority' => ['temporal_mode' => 'current', 'evaluated_at' => '2026-11-06T10:27:00+00:00'],
            'resolved_applicability_location' => ['resolved_location_public_id' => null]];
        $candidate = ['chunk_id' => $bannerChunk->public_id, 'document_id' => $chunk->document->public_id,
            'document_family_id' => $chunk->document->family->public_id, 'chunk_text' => $banner,
            'side' => 'primary', 'need_id' => 'n1'];
        $input = new GenerationAssemblyInput($base->question,
            new RetrievalResult(RetrievalOutcome::EvidenceFound, [$candidate], evidenceNeeds: [$need]),
            [], [], $base->authorisedScope, []);

        $evidence = app(AssembleGenerationRequest::class)->handle($input)->toArray()['evidence'][0];
        $this->assertSame($banner, $evidence['text']);
        $this->assertSame('2026-11-06T10:27:00+00:00', $evidence['temporal_authority']['evaluated_at']);
        $this->assertSame('2026-09-01T00:00:00+00:00', $evidence['temporal_authority']['authority_start']);
        $this->assertTrue($evidence['temporal_authority']['authoritative_at_evaluated_at']);
    }

    public static function needsAssemblyMutations(): array
    {
        return [['valid'], ['budget'], ['missing_side'], ['wrong_family']];
    }

    #[DataProvider('needsAssemblyMutations')]
    public function test_needs_assembly_preserves_owned_pairs_and_current_support(string $mutation): void
    {
        [$base, $first] = $this->assemblyFixture();
        $workspace = $base->authorisedScope->workspace;
        $previous = Document::factory()->indexed()->create(['workspace_id' => $workspace->id,
            'document_family_id' => $first->document->document_family_id, 'predecessor_document_id' => $first->document_id]);
        $support = Document::factory()->indexed()->create(['workspace_id' => $workspace->id]);
        $chunks = [$first];
        foreach ([$previous, $support] as $document) {
            $claim = IngestionEventClaim::factory()->create(['workspace_id' => $workspace->id, 'document_id' => $document->id]);
            $chunks[] = DocumentChunk::factory()->create(['workspace_id' => $workspace->id,
                'document_id' => $document->id, 'ingestion_event_claim_id' => $claim->id, 'text' => 'Synthetic supporting evidence.']);
        }
        $candidates = [];
        foreach ($chunks as $i => $chunk) {
            $candidates[] = ['chunk_id' => $chunk->public_id, 'document_id' => $chunk->document->public_id,
                'document_family_id' => $chunk->document->family->public_id, 'chunk_text' => $chunk->text,
                'side' => $i === 1 ? 'comparison' : 'primary', 'need_id' => $i === 2 ? 'n2' : 'n1'];
        }
        $examples = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/examples.json'), true, flags: JSON_THROW_ON_ERROR);
        $needs = array_map(fn ($need) => [...$need, 'retrieval_outcome' => 'EVIDENCE_FOUND', 'retrieval_reason' => null,
            'resolved_temporal_authority' => ['mode' => $need['operation']],
            'resolved_applicability_location' => ['resolved_location_public_id' => null]], $examples[0]['plan']['needs']);
        if ($mutation === 'budget') {
            config(['generation.max_context_characters' => 1]);
            $this->expectException(GenerationContextBudgetExceeded::class);
        } elseif ($mutation === 'missing_side') {
            unset($candidates[1]);
            $candidates = array_values($candidates);
            $this->expectException(GenerationException::class);
        } elseif ($mutation === 'wrong_family') {
            $candidates[1]['document_family_id'] = (string) Str::uuid();
            $this->expectException(GenerationException::class);
        }
        $input = new GenerationAssemblyInput($base->question,
            new RetrievalResult(RetrievalOutcome::EvidenceFound, $candidates, evidenceNeeds: $needs),
            [], [], $base->authorisedScope, []);
        $request = app(AssembleGenerationRequest::class)->handle($input);
        $wire = $request->toArray();
        $this->assertSame(2, $wire['contract_version']);
        $this->assertCount(3, $wire['evidence']);
        $this->assertSame(['ev-01', 'ev-02'], $wire['evidence_needs'][0]['evidence_ids']);
        $this->assertSame(['ev-03'], $wire['evidence_needs'][1]['evidence_ids']);
        $this->assertSame('n2', $wire['evidence'][2]['temporal_authority']['need_id']);
        $this->assertSame($first->document->source_filename, $wire['evidence'][0]['temporal_authority']['document_title']);
        $this->assertSame($first->document->family->name, $wire['evidence'][0]['temporal_authority']['document_family_title']);
        $this->assertArrayNotHasKey('supports_need_id', $wire['evidence_needs'][1]);
        unset($needs[1]['supports_need_id']);
        $withoutEdge = app(AssembleGenerationRequest::class)->handle(new GenerationAssemblyInput($base->question,
            new RetrievalResult(RetrievalOutcome::EvidenceFound, $candidates, evidenceNeeds: $needs), [], [], $base->authorisedScope, []))->toArray();
        unset($wire['request_id'], $withoutEdge['request_id']);
        $this->assertSame($wire, $withoutEdge);
        Http::preventStrayRequests();
        Http::fake(function ($sent) {
            $this->assertStringEndsWith('/generation/answer-needs', $sent->url());
            $this->assertSame(['generation.answer-needs'], $sent->header('X-Retrieval-Caller-Purpose'));
            $this->assertSame(2, $sent->data()['contract_version']);

            return Http::response(['contract_version' => 2, 'request_id' => $sent->data()['request_id'],
                'status' => 'completed', 'result' => ['contract_version' => 2, 'outcome' => 'answered',
                    'answer_parts' => [['text' => 'Synthetic paired answer.', 'evidence_ids' => ['ev-01', 'ev-02']],
                        ['text' => 'Independent current guidance.', 'evidence_ids' => ['ev-03']]],
                    'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
                    'aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []],
                        ['need_id' => 'n2', 'answer_part_indices' => [1], 'unsupported_aspect_indices' => []]]]]);
        });
        $answer = app(GenerateGroundedAnswer::class)->handle($input, app(GenerationProfileFactory::class)->configuredNeeds());
        $this->assertSame(['n1' => 'supported', 'n2' => 'supported'], $answer->aspect_results);
        $this->assertCount(3, $answer->evidenceSnapshots);
        Http::assertSentCount(1);
    }

    public static function needsCases(): iterable
    {
        $fixtures = json_decode((string) file_get_contents('/contracts/fixtures/generation-aspect-coverage/v1.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($fixtures['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    public static function validationRetryScenarios(): array
    {
        return [['local_then_valid', 2], ['remote_then_valid', 2], ['twice_invalid', 2],
            ['invalid_then_transport', 2], ['invalid_then_replay', 2], ['transport', 1], ['insufficient', 1]];
    }

    #[DataProvider('validationRetryScenarios')]
    public function test_bounded_validation_retry_refreshes_only_identity_and_retains_usage(string $scenario, int $expected): void
    {
        [$base, $chunk] = $this->assemblyFixture();
        $examples = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/examples.json'), true, flags: JSON_THROW_ON_ERROR);
        $need = $examples[0]['plan']['needs'][1];
        $need['need_id'] = 'n1';
        unset($need['supports_need_id']);
        $need += ['retrieval_outcome' => 'EVIDENCE_FOUND', 'retrieval_reason' => null,
            'resolved_temporal_authority' => ['mode' => 'current'],
            'resolved_applicability_location' => ['resolved_location_public_id' => null]];
        $candidate = ['chunk_id' => $chunk->public_id, 'document_id' => $chunk->document->public_id,
            'document_family_id' => $chunk->document->family->public_id, 'chunk_text' => $chunk->text,
            'side' => 'primary', 'need_id' => 'n1'];
        $input = new GenerationAssemblyInput($base->question,
            new RetrievalResult(RetrievalOutcome::EvidenceFound, [$candidate], evidenceNeeds: [$need]),
            [], [], $base->authorisedScope, []);
        $calls = [];
        $signatures = [];
        Http::preventStrayRequests();
        Http::fake(function ($sent) use (&$calls, &$signatures, $scenario) {
            $calls[] = $sent->body();
            $signatures[] = $sent->header('X-Retrieval-Caller-Signature');
            $count = count($calls);
            if ($scenario === 'invalid_then_replay' && $count === 2) {
                return Http::response(['detail' => 'PRIVATE'], 401, ['X-Retrieval-Rejection' => 'replay']);
            }
            if ($scenario === 'transport' || ($scenario === 'invalid_then_transport' && $count === 2)) {
                throw new ConnectionException('synthetic transport failure');
            }
            $usage = ['request_count' => 1, 'retry_count' => 0, 'input_tokens' => 100,
                'output_tokens' => 20, 'cached_input_tokens' => 0, 'latency_ms' => 10, 'cost_usd' => 0.001];
            if ($scenario === 'remote_then_valid' && $count === 1) {
                return Http::response(['contract_version' => 2, 'request_id' => $sent->data()['request_id'],
                    'status' => 'provider_error', 'error' => ['category' => 'contract_validation_failure',
                        'provider' => 'openai', 'model' => 'synthetic', 'http_status' => 200,
                        'attempt_count' => 1, 'latency_ms' => 10, 'returned_output' => true,
                        'validation' => ['field' => 'answer_parts.text', 'code' => 'detached_polarity'], 'usage' => $usage]]);
            }
            $invalid = $scenario === 'twice_invalid' || ($count === 1 && in_array($scenario, ['local_then_valid', 'invalid_then_transport', 'invalid_then_replay'], true));
            $insufficient = $scenario === 'insufficient';

            return Http::response(['contract_version' => 2, 'request_id' => $sent->data()['request_id'], 'status' => 'completed',
                'result' => ['contract_version' => 2, 'outcome' => $insufficient ? 'insufficient_evidence' : 'answered',
                    'answer_parts' => $insufficient ? [] : [['text' => 'Supported fictional statement.', 'evidence_ids' => [$invalid ? 'ev-99' : 'ev-01']]],
                    'unsupported_aspects' => $insufficient ? ['Requested information.'] : [],
                    'insufficiency_reason' => $insufficient ? 'No supporting passage.' : null, 'usage' => $usage,
                    'aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => $insufficient ? [] : [0],
                        'unsupported_aspect_indices' => $insufficient ? [0] : []]]]]);
        });
        try {
            $prepared = app(GenerateGroundedAnswer::class)->prepare($input, app(GenerationProfileFactory::class)->configuredNeeds());
            $this->assertNotContains($scenario, ['twice_invalid', 'transport', 'invalid_then_transport', 'invalid_then_replay']);
            $this->assertSame(100 * $expected, $prepared->result->usage['input_tokens']);
            $this->assertSame(20 * $expected, $prepared->result->usage['output_tokens']);
            $this->assertSame(10 * $expected, $prepared->result->usage['latency_ms']);
            $this->assertEquals(0.001 * $expected, $prepared->result->usage['cost_usd']);
            $this->assertCount($expected, $prepared->result->usage['validation_attempts']);
        } catch (GenerationException $exception) {
            $this->assertContains($scenario, ['twice_invalid', 'transport', 'invalid_then_transport', 'invalid_then_replay']);
            $this->assertCount($expected, $exception->validationAttempts);
            if ($scenario === 'invalid_then_replay') {
                $second = $exception->validationAttempts[1];
                $this->assertSame('replay_rejected', $second['validation']['code']);
                $this->assertSame('python_service_authentication', $second['validation']['layer']);
                $this->assertSame(401, $second['validation']['http_status']);
                $this->assertSame(0, $second['request_count']);
                $this->assertSame(0, $second['input_tokens']);
                $this->assertSame(0, $second['cost_usd']);
                $this->assertStringNotContainsString('PRIVATE', json_encode($second));
            }
            if ($expected === 2) {
                $this->assertSame(100, $exception->validationAttempts[0]['input_tokens']);
                $this->assertSame('citation_membership', $exception->validationAttempts[0]['validation']['code']);
            }
        }
        $this->assertCount($expected, $calls);
        if ($expected === 2) {
            $first = json_decode($calls[0], true, flags: JSON_THROW_ON_ERROR);
            $second = json_decode($calls[1], true, flags: JSON_THROW_ON_ERROR);
            $this->assertNotSame($first['request_id'], $second['request_id']);
            $this->assertNotSame(hash('sha256', $calls[0]), hash('sha256', $calls[1]));
            $this->assertNotSame($signatures[0], $signatures[1]);
            $history = isset($prepared) ? $prepared->result->usage['validation_attempts'] : $exception->validationAttempts;
            $this->assertSame($first['request_id'], $history[0]['request_id']);
            $this->assertSame($second['request_id'], $history[1]['request_id']);
            $this->assertSame($first['request_id'], $history[1]['retry_of_request_id']);
            if (isset($prepared)) {
                $this->assertSame($second['request_id'], $prepared->request->requestId);
                $this->assertSame($first['request_id'], $prepared->correlationId);
            }
            unset($first['request_id'], $second['request_id']);
            $this->assertSame(json_encode($first, JSON_THROW_ON_ERROR), json_encode($second, JSON_THROW_ON_ERROR));
        }
    }

    #[DataProvider('needsCases')]
    public function test_v2_consumer_validates_shared_coverage_and_derives_status(array $case): void
    {
        if (! $case['valid']) {
            $this->expectException(GenerationException::class);
        }
        $result = app(ValidateGenerationResult::class)->handleNeeds($this->simpleRequest(), $case['need_ids'],
            [...$case['result'], 'contract_version' => 2, 'usage' => null, 'aspect_links' => $case['links']]);
        $this->assertSame($case['expected_statuses'], $result->aspectResults);
    }

    public function test_v2_coverage_persists_with_the_authoritative_text_and_citation(): void
    {
        [$input] = $this->assemblyFixture();
        $request = app(AssembleGenerationRequest::class)->handle($input);
        $result = app(ValidateGenerationResult::class)->handleNeeds($request, ['n1', 'n2'], [
            'contract_version' => 2, 'outcome' => 'qualified',
            'answer_parts' => [['text' => 'The historical change is not established. The current procedure requires recording the assessment.', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => ['The historical change is not established.'], 'insufficiency_reason' => null, 'usage' => null,
            'aspect_links' => [
                ['need_id' => 'n1', 'answer_part_indices' => [], 'unsupported_aspect_indices' => [0]],
                ['need_id' => 'n2', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []],
            ],
        ]);
        $fingerprint = app(GenerationFingerprint::class)->make('deterministic', 'contract-fake', 'generation-result-v2', 'test-only', 'test-only', []);
        $answer = app(PersistGeneratedAnswer::class)->handle($input->authorisedScope, $input->question,
            (string) Str::uuid(), $request, $result, $fingerprint)->fresh();
        $this->assertSame($result->aspectLinks, $answer->aspect_links);
        $this->assertSame($result->aspectResults, $answer->aspect_results);
        $this->assertSame($result->renderedText(), $answer->renderedText());
        $this->assertCount(1, $answer->evidenceSnapshots);
    }

    public static function invalidNeedsCitations(): array
    {
        return [[['ev02']], [['ev-99']], [['ev-01', 'ev-01']], [[]]];
    }

    public function test_missing_need_cannot_borrow_another_needs_answer(): void
    {
        $base = $this->simpleRequest();
        $request = new GenerationRequest($base->requestId, $base->workspacePublicId, $base->question,
            $base->evidence, $base->contextPolicyVersion, $base->maxContextCharacters,
            $base->requiredSides, $base->requestedEvidenceType, [
                ['need_id' => 'n1', 'evidence_ids' => []],
                ['need_id' => 'n2', 'evidence_ids' => ['ev-01']],
            ]);
        $payload = ['contract_version' => 2, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'Current guidance.', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
            'aspect_links' => [
                ['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []],
                ['need_id' => 'n2', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []],
            ]];
        try {
            app(ValidateGenerationResult::class)->handleNeeds($request, ['n1', 'n2'], $payload);
            $this->fail('Cross-need citation accepted.');
        } catch (GenerationException $exception) {
            $diagnostic = GenerationValidationDiagnostics::local($exception);
            $this->assertSame('citation_ownership', $diagnostic['code']);
            $this->assertSame(['need_id' => 'n1', 'answer_part_index' => 0,
                'cited_ids' => ['ev-01'], 'allowed_ids' => []], $diagnostic['ownership']);
        }
        $payload['outcome'] = 'qualified';
        $payload['unsupported_aspects'] = ['The requested history is unavailable in this evidence.'];
        $payload['aspect_links'][0] = ['need_id' => 'n1', 'answer_part_indices' => [], 'unsupported_aspect_indices' => [0]];
        $result = app(ValidateGenerationResult::class)->handleNeeds($request, ['n1', 'n2'], $payload);
        $this->assertSame(['n1' => 'unsupported', 'n2' => 'supported'], $result->aspectResults);
    }

    #[DataProvider('invalidNeedsCitations')]
    public function test_v2_does_not_relax_citation_validation(array $ids): void
    {
        $this->expectException(GenerationException::class);
        app(ValidateGenerationResult::class)->handleNeeds($this->simpleRequest(), ['n1'], [
            'contract_version' => 2, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'A claim.', 'evidence_ids' => $ids]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
            'aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []]],
        ]);
    }

    public function test_v2_result_cannot_be_persisted_under_a_legacy_profile(): void
    {
        [$input] = $this->assemblyFixture();
        $request = app(AssembleGenerationRequest::class)->handle($input);
        $result = app(ValidateGenerationResult::class)->handleNeeds($request, ['n1'], [
            'contract_version' => 2, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'A grounded claim.', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
            'aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []]],
        ]);
        $fingerprint = app(GenerationFingerprint::class)->make('deterministic', 'contract-fake', 'generation-result-v1', 'test-only', 'test-only', []);
        try {
            app(PersistGeneratedAnswer::class)->handle($input->authorisedScope, $input->question,
                (string) Str::uuid(), $request, $result, $fingerprint);
            $this->fail('A v2 result must not use a legacy profile.');
        } catch (GenerationException) {
            $this->assertDatabaseCount('generated_answers', 0);
        }
    }

    public function test_outcome_and_citation_validation_fail_closed(): void
    {
        $request = $this->simpleRequest();
        $validator = app(ValidateGenerationResult::class);
        $answered = $validator->handle($request, [
            'contract_version' => 1, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'Grounded prose.', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
        ]);
        $this->assertSame(GenerationOutcome::Answered, $answered->outcome);
        $this->assertSame('Grounded prose.', $answered->renderedText());

        $this->expectException(GenerationException::class);
        $validator->handle($request, [
            'contract_version' => 1, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'Unsafe.', 'evidence_ids' => ['ev-99']]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
        ]);
    }

    public function test_actual_validator_failures_have_safe_rule_diagnostics(): void
    {
        $base = ['contract_version' => 2, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'PRIVATE-CONTENT', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
            'aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []]]];
        $mutations = [
            'needs_schema' => fn ($p) => array_replace($p, ['contract_version' => 99]),
            'result_fields' => fn ($p) => array_replace($p, ['unsupported_aspects' => ['']]),
            'part_shape' => fn ($p) => array_replace($p, ['answer_parts' => [['text' => 'PRIVATE-CONTENT', 'evidence_ids' => []]]]),
            'citation_membership' => fn ($p) => array_replace($p, ['answer_parts' => [['text' => 'PRIVATE-CONTENT', 'evidence_ids' => ['ev-99']]]]),
            'outcome_invariant' => fn ($p) => array_replace($p, ['insufficiency_reason' => 'PRIVATE-CONTENT']),
            'usage_shape' => fn ($p) => array_replace($p, ['usage' => 'PRIVATE-CONTENT']),
            'need_identity' => fn ($p) => array_replace($p, ['aspect_links' => []]),
            'need_coverage' => fn ($p) => array_replace($p, ['aspect_links' => [['need_id' => 'n2', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []]]]),
            'empty_need' => fn ($p) => array_replace($p, ['aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => [], 'unsupported_aspect_indices' => []]]]),
            'reference_range' => fn ($p) => array_replace($p, ['aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => [99], 'unsupported_aspect_indices' => []]]]),
            'reference_list' => fn ($p) => array_replace($p, ['aspect_links' => [['need_id' => 'n1', 'answer_part_indices' => 'PRIVATE-CONTENT', 'unsupported_aspect_indices' => []]]]),
            'unmapped_content' => fn ($p) => array_replace($p, ['answer_parts' => [$p['answer_parts'][0], $p['answer_parts'][0]]]),
        ];
        foreach ($mutations as $code => $mutate) {
            try {
                app(ValidateGenerationResult::class)->handleNeeds($this->simpleRequest(), ['n1'], $mutate($base));
                $this->fail('Mutation accepted: '.$code);
            } catch (GenerationException $exception) {
                $diagnostic = GenerationValidationDiagnostics::local($exception);
                $this->assertSame($code, $diagnostic['code']);
                $this->assertSame('laravel_result_validation', $diagnostic['layer']);
                $this->assertStringNotContainsString('PRIVATE', json_encode($diagnostic));
            }
        }
    }

    public function test_provider_cannot_supply_persistent_answer_part_identity(): void
    {
        $request = $this->simpleRequest();

        $this->expectException(GenerationException::class);
        app(ValidateGenerationResult::class)->handle($request, [
            'contract_version' => 1, 'outcome' => 'answered',
            'answer_parts' => [[
                'id' => (string) Str::uuid(),
                'text' => 'Grounded prose.',
                'evidence_ids' => ['ev-01'],
            ]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
        ]);
    }

    public function test_insufficiency_reason_is_structurally_bounded(): void
    {
        $this->expectException(GenerationException::class);
        app(ValidateGenerationResult::class)->handle($this->simpleRequest(), [
            'contract_version' => 1,
            'outcome' => 'insufficient_evidence',
            'answer_parts' => [],
            'unsupported_aspects' => ['duration'],
            'insufficiency_reason' => str_repeat('x', 8001),
            'usage' => null,
        ]);
    }

    public function test_context_packing_preserves_compare_sides_and_fails_when_structural_minimum_cannot_fit(): void
    {
        $primary = $this->evidence('ev-01', str_repeat('p', 10), GenerationSide::Primary);
        $optional = $this->evidence('ev-02', str_repeat('o', 20), GenerationSide::Primary);
        $comparison = $this->evidence('ev-03', str_repeat('c', 10), GenerationSide::Comparison);
        $optionalComparison = $this->evidence('ev-04', str_repeat('x', 3), GenerationSide::Comparison);
        $packer = app(GenerationContextPacker::class);
        $packed = $packer->pack([$primary, $optional, $comparison, $optionalComparison], [GenerationSide::Primary, GenerationSide::Comparison], 23, 'whole-evidence-v1');
        $this->assertSame(['ev-01', 'ev-03', 'ev-04'], array_map(fn (GenerationEvidence $item): string => $item->evidenceId, $packed));

        $this->expectException(GenerationContextBudgetExceeded::class);
        $packer->pack([$primary, $comparison], [GenerationSide::Primary, GenerationSide::Comparison], 19, 'whole-evidence-v1');
    }

    public function test_current_context_packing_preserves_whole_evidence_and_deterministic_order(): void
    {
        $first = $this->evidence('ev-01', 'first', GenerationSide::Primary);
        $second = $this->evidence('ev-02', 'second', GenerationSide::Primary);
        $third = $this->evidence('ev-03', 'third', GenerationSide::Primary);

        $packed = app(GenerationContextPacker::class)->pack(
            [$first, $second, $third],
            [GenerationSide::Primary],
            11,
            'whole-evidence-v1',
        );

        $this->assertSame(['ev-01', 'ev-02'], array_map(
            fn (GenerationEvidence $item): string => $item->evidenceId,
            $packed,
        ));
        $this->assertSame(['first', 'second'], array_map(
            fn (GenerationEvidence $item): string => $item->text,
            $packed,
        ));
    }

    public function test_generation_persists_one_stable_snapshot_reused_by_multiple_parts(): void
    {
        [$input, $chunk] = $this->assemblyFixture();
        Http::fake(fn ($request) => Http::response([
            'contract_version' => 1,
            'request_id' => $request->data()['request_id'],
            'status' => 'completed',
            'result' => [
                'contract_version' => 1, 'outcome' => 'answered',
                'answer_parts' => [
                    ['text' => 'First grounded part.', 'evidence_ids' => ['ev-01']],
                    ['text' => 'Second grounded part.', 'evidence_ids' => ['ev-01']],
                ],
                'unsupported_aspects' => [], 'insufficiency_reason' => null,
                'usage' => ['latency_ms' => 2, 'input_tokens' => null, 'output_tokens' => null, 'cost_usd' => null],
            ],
        ]));
        $profile = new GenerationProfile('deterministic', 'contract-fake', 'generation-v1', 'none', 'fake-v1', GenerationDeliveryMode::CompleteResultOnly, ['response_mode' => 'fixture']);
        $answer = app(GenerateGroundedAnswer::class)->handle($input, $profile);

        $this->assertCount(2, $answer->answerParts);
        $this->assertNull($answer->aspect_links);
        $this->assertNull($answer->aspect_results);
        $this->assertSame("First grounded part.\n\nSecond grounded part.", $answer->renderedText());
        $this->assertCount(1, $answer->evidenceSnapshots);
        $snapshot = $answer->evidenceSnapshots->firstOrFail();
        $this->assertSame($chunk->text, $snapshot->cited_text_verbatim);
        $this->assertSame($chunk->provenance, $snapshot->source_provenance);
        $this->assertSame(hash('sha256', $chunk->text), $snapshot->content_digest);
        $this->assertSame($chunk->public_id, $snapshot->source_chunk_public_id);
        $this->assertSame($chunk->ordinal, $snapshot->source_chunk_ordinal);
        $this->assertSame($chunk->ingestionAttempt->event_id, $snapshot->source_ingestion_event_id);
        $this->assertSame($snapshot->id, $answer->answerParts[0]->evidenceSnapshots[0]->id);
        $this->assertSame($snapshot->id, $answer->answerParts[1]->evidenceSnapshots[0]->id);
        $this->assertNotSame('ev-01', $answer->answerParts[0]->public_id);
        DB::table('document_chunks')->where('id', $chunk->id)->delete();
        $this->assertNull($snapshot->fresh()->document_chunk_id);
        $this->assertSame($chunk->text, $snapshot->fresh()->cited_text_verbatim);
        $this->assertSame($chunk->public_id, $snapshot->fresh()->source_chunk_public_id);
    }

    public function test_fractional_provider_latency_persists_with_answer_parts_and_citations(): void
    {
        [$input] = $this->assemblyFixture();
        $request = app(AssembleGenerationRequest::class)->handle($input);
        $result = new GenerationResult(
            GenerationOutcome::Answered,
            [new AnswerPartResult('Persisted with its citation.', ['ev-01'])],
            [],
            null,
            ['latency_ms' => 9528.4155469853, 'input_tokens' => 10, 'output_tokens' => 5, 'cost_usd' => 0.001],
        );
        $fingerprint = app(GenerationFingerprint::class)->make(
            'openai',
            'gpt-5-mini',
            'generation-result-v1',
            'grounded-generation-v2',
            'openai-responses-v2',
            ['response_mode' => 'fixture'],
        );

        $answer = app(PersistGeneratedAnswer::class)->handle(
            $input->authorisedScope,
            $input->question,
            (string) Str::uuid(),
            $request,
            $result,
            $fingerprint,
        );

        $this->assertSame(9528, $answer->latency_ms);
        $this->assertSame(9528.4155469853, $answer->usage['latency_ms']);
        $this->assertCount(1, $answer->answerParts);
        $this->assertCount(1, $answer->answerParts->firstOrFail()->evidenceSnapshots);
    }

    #[DataProvider('invalidLatencyValues')]
    public function test_invalid_provider_latency_is_rejected_without_persisting_an_answer(mixed $latency): void
    {
        [$input] = $this->assemblyFixture();
        $request = app(AssembleGenerationRequest::class)->handle($input);
        $result = new GenerationResult(
            GenerationOutcome::Answered,
            [new AnswerPartResult('Must not persist.', ['ev-01'])],
            [],
            null,
            ['latency_ms' => $latency],
        );
        $fingerprint = app(GenerationFingerprint::class)->make(
            'openai', 'gpt-5-mini', 'generation-result-v1', 'grounded-generation-v2',
            'openai-responses-v2', ['response_mode' => 'fixture'],
        );

        try {
            app(PersistGeneratedAnswer::class)->handle(
                $input->authorisedScope,
                $input->question,
                (string) Str::uuid(),
                $request,
                $result,
                $fingerprint,
            );
            $this->fail('Expected invalid latency to fail closed.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('generated_answers', 0);
            $this->assertDatabaseCount('answer_parts', 0);
            $this->assertDatabaseCount('evidence_snapshots', 0);
        }
    }

    /** @return array<string, array{mixed}> */
    public static function invalidLatencyValues(): array
    {
        return [
            'negative' => [-0.1],
            'positive infinity' => [INF],
            'not a number' => [NAN],
            'outside unsigned integer range' => [4_294_967_296.0],
            'string' => ['9528.4'],
        ];
    }

    public function test_child_persistence_failure_rolls_back_the_complete_answer_graph(): void
    {
        [$input] = $this->assemblyFixture();
        $request = app(AssembleGenerationRequest::class)->handle($input);
        $result = new GenerationResult(
            GenerationOutcome::Answered,
            [new AnswerPartResult('Invalid duplicate-link fixture.', ['ev-01', 'ev-01'])],
            [],
            null,
        );
        $fingerprint = app(GenerationFingerprint::class)->make(
            'deterministic',
            'contract-fake',
            'generation-v1',
            'none',
            'fake-v1',
            ['response_mode' => 'fixture'],
        );

        try {
            app(PersistGeneratedAnswer::class)->handle(
                $input->authorisedScope,
                $input->question,
                (string) Str::uuid(),
                $request,
                $result,
                $fingerprint,
            );
            $this->fail('Expected the duplicate child link to fail.');
        } catch (QueryException) {
            $this->assertDatabaseCount('generated_answers', 0);
            $this->assertDatabaseCount('answer_parts', 0);
            $this->assertDatabaseCount('evidence_snapshots', 0);
            $this->assertDatabaseCount('answer_part_evidence_snapshot', 0);
        }
    }

    public function test_cross_workspace_evidence_cannot_enter_generation_request(): void
    {
        [$input] = $this->assemblyFixture();
        $other = Workspace::factory()->create();
        $foreignInput = new GenerationAssemblyInput(
            $input->question,
            $input->retrievalResult,
            $input->resolvedTemporalAuthority,
            $input->resolvedApplicabilityLocation,
            new AuthorisedKnowledgeScope($input->authorisedScope->user, $other, null),
            $input->correlationLineage,
        );

        $this->expectException(GenerationException::class);
        app(AssembleGenerationRequest::class)->handle($foreignInput);
    }

    public function test_generation_fingerprint_is_deterministic_and_quality_sensitive(): void
    {
        $service = app(GenerationFingerprint::class);
        $first = $service->make('provider', 'model', '1', 'p1', 'a1', [
            'max_output_tokens' => 800,
            'sampling' => ['temperature' => 0, 'top_p' => 1],
        ]);
        $same = $service->make('provider', 'model', '1', 'p1', 'a1', [
            'sampling' => ['top_p' => 1, 'temperature' => 0],
            'max_output_tokens' => 800,
        ]);
        $changed = $service->make('provider', 'model', '1', 'p1', 'a1', [
            'max_output_tokens' => 801,
            'sampling' => ['temperature' => 0, 'top_p' => 1],
        ]);
        $this->assertSame($first['generation_fingerprint'], $same['generation_fingerprint']);
        $this->assertNotSame($first['generation_fingerprint'], $changed['generation_fingerprint']);
        $this->assertSame(1, $first['fingerprint_scheme_version']);
        $this->assertSame('614ebe8ad04f52d681a5b62b6d0a41883a42f8386c36251fc470d18e44d2ba6d', $first['generation_fingerprint']);
    }

    public function test_configured_openai_generation_profile_matches_python_adapter_lineage(): void
    {
        $profile = app(GenerationProfileFactory::class)->configured();
        $fingerprint = app(GenerationFingerprint::class)->make(
            $profile->provider,
            $profile->model,
            $profile->contractVersion,
            $profile->promptVersion,
            $profile->adapterVersion,
            $profile->qualityAffectingConfiguration,
        );

        $this->assertSame('openai', $profile->provider);
        $this->assertSame('gpt-5-mini', $profile->model);
        $this->assertSame('generation-result-v1', $profile->contractVersion);
        $this->assertSame('grounded-generation-v3-claim-type', $profile->promptVersion);
        $this->assertSame('openai-responses-v3-claim-type', $profile->adapterVersion);
        $this->assertSame(GenerationDeliveryMode::StreamingParts, $profile->deliveryMode);
        $this->assertSame(
            '5982f6a7a860056e53a33b9aea23746d8273cda9cdae4c55fc6f0403c4388dbe',
            $fingerprint['generation_fingerprint'],
        );
    }

    public function test_rc1_budget_failure_remains_typed_and_distinct(): void
    {
        $workspace = Workspace::factory()->create();
        $request = $this->simpleRequest();
        Http::fake(fn () => Http::response([
            'contract_version' => 1, 'request_id' => $request->requestId,
            'status' => 'context_budget_exceeded',
            'failure' => [
                'code' => 'GENERATION_CONTEXT_BUDGET_EXCEEDED',
                'policy_version' => 'whole-evidence-v1', 'proposed_units' => 101, 'maximum_units' => 100,
            ],
        ]));
        try {
            app(GenerationClient::class)->generate($workspace, $request);
            $this->fail('Expected the structural budget exception.');
        } catch (GenerationContextBudgetExceeded $exception) {
            $this->assertSame(101, $exception->proposedUnits);
        }
    }

    public function test_rc1_provider_failure_remains_typed_and_distinct(): void
    {
        $workspace = Workspace::factory()->create();
        $request = $this->simpleRequest();
        Http::fake(fn () => Http::response([
            'contract_version' => 1, 'request_id' => $request->requestId,
            'status' => 'provider_error',
            'error' => [
                'category' => 'timeout', 'provider' => null, 'model' => null,
                'http_status' => null, 'attempt_count' => 1, 'latency_ms' => 12.5,
            ],
        ]));
        $this->expectException(GenerationProviderException::class);
        app(GenerationClient::class)->generate($workspace, $request);
    }

    public function test_rc1_generation_stream_validates_sequence_and_returns_independent_terminal_result(): void
    {
        $workspace = Workspace::factory()->create();
        $request = $this->simpleRequest();
        $candidate = $this->streamEvent($request, 1, 'answer_part_candidate', 'candidate', ['text' => 'Grounded.', 'evidence_ids' => ['ev-01']]);
        $result = [
            'contract_version' => 1, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'Grounded.', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
        ];
        $terminal = $this->streamEvent($request, 2, 'generation_completed', 'result', $result);
        Http::fake(fn () => Http::response(json_encode($candidate, JSON_THROW_ON_ERROR)."\n".json_encode($terminal, JSON_THROW_ON_ERROR)."\n"));
        $observed = [];

        $returned = app(GenerationClient::class)->stream($workspace, $request, function (array $part) use (&$observed): void {
            $observed[] = $part;
        });

        $this->assertSame([$candidate['candidate']], $observed);
        $this->assertSame($result, $returned);
        Http::assertSent(fn ($sent): bool => $sent->hasHeader('X-Retrieval-Caller-Purpose', 'generation.stream'));
    }

    public function test_rc1_generation_stream_fails_closed_on_sequence_gap(): void
    {
        $workspace = Workspace::factory()->create();
        $request = $this->simpleRequest();
        $terminal = $this->streamEvent($request, 2, 'generation_completed', 'result', [
            'contract_version' => 1, 'outcome' => 'answered',
            'answer_parts' => [['text' => 'Grounded.', 'evidence_ids' => ['ev-01']]],
            'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
        ]);
        Http::fake(fn () => Http::response(json_encode($terminal, JSON_THROW_ON_ERROR)."\n"));

        $this->expectException(GenerationStreamException::class);
        app(GenerationClient::class)->stream($workspace, $request, fn () => null);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function streamEvent(GenerationRequest $request, int $sequence, string $type, string $field, array $payload): array
    {
        return [
            'contract_version' => 1, 'request_id' => $request->requestId, 'sequence' => $sequence,
            'event_type' => $type, 'candidate' => null, 'result' => null, 'error' => null, 'failure' => null,
            $field => $payload,
        ];
    }

    private function simpleRequest(): GenerationRequest
    {
        return new GenerationRequest((string) Str::uuid(), (string) Str::uuid(), 'Question?', [$this->evidence('ev-01', 'Evidence.', GenerationSide::Primary)], 'whole-evidence-v1', 100, [GenerationSide::Primary]);
    }

    private function evidence(string $id, string $text, GenerationSide $side): GenerationEvidence
    {
        return new GenerationEvidence($id, $text, 1, 1, 1, [['kind' => 'text']], ['mode' => 'current'], ['scope' => 'universal'], $side);
    }

    /** @return array{GenerationAssemblyInput, DocumentChunk} */
    private function assemblyFixture(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by_user_id' => $user->id]);
        $document = Document::factory()->indexed()->create(['workspace_id' => $workspace->id, 'created_by_user_id' => $user->id]);
        $claim = IngestionEventClaim::factory()->create(['workspace_id' => $workspace->id, 'document_id' => $document->id]);
        $chunk = DocumentChunk::factory()->create([
            'workspace_id' => $workspace->id, 'document_id' => $document->id,
            'ingestion_event_claim_id' => $claim->id, 'ordinal' => 1,
            'text' => "  Keep this exact table-style evidence.\n  | A | B |",
            'content_digest' => hash('sha256', "  Keep this exact table-style evidence.\n  | A | B |"),
        ]);
        $retrieval = new RetrievalResult(RetrievalOutcome::EvidenceFound, [[
            'chunk_id' => $chunk->public_id, 'document_id' => $document->public_id,
            'chunk_text' => $chunk->text, 'side' => 'primary', 'rank' => 1,
        ]]);
        $scope = new AuthorisedKnowledgeScope($user, $workspace, null);

        return [new GenerationAssemblyInput('What applies?', $retrieval, ['mode' => 'current'], ['scope' => 'universal'], $scope, ['correlation_id' => (string) Str::uuid()]), $chunk];
    }
}
