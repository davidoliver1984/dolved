<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Generation\GenerateGroundedAnswer;
use App\Actions\Generation\PersistGeneratedAnswer;
use App\Enums\GenerationOutcome;
use App\Enums\GenerationSide;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationContextBudgetExceeded;
use App\Exceptions\GenerationException;
use App\Exceptions\GenerationProviderException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Generation\AssembleGenerationRequest;
use App\Services\Generation\GenerationClient;
use App\Services\Generation\GenerationContextPacker;
use App\Services\Generation\GenerationFingerprint;
use App\Services\Generation\ValidateGenerationResult;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationEvidence;
use App\Support\Generation\GenerationProfile;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\GenerationResult;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\RetrievalResult;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroundedGenerationFoundationTest extends TestCase
{
    use RefreshDatabase;

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
        $profile = new GenerationProfile('deterministic', 'contract-fake', 'generation-v1', 'none', 'fake-v1', ['response_mode' => 'fixture']);
        $answer = app(GenerateGroundedAnswer::class)->handle($input, $profile);

        $this->assertCount(2, $answer->answerParts);
        $this->assertSame("First grounded part.\n\nSecond grounded part.", $answer->renderedText());
        $this->assertCount(1, $answer->evidenceSnapshots);
        $snapshot = $answer->evidenceSnapshots->firstOrFail();
        $this->assertSame($chunk->text, $snapshot->cited_text_verbatim);
        $this->assertSame($chunk->provenance, $snapshot->source_provenance);
        $this->assertSame(hash('sha256', $chunk->text), $snapshot->content_digest);
        $this->assertSame($snapshot->id, $answer->answerParts[0]->evidenceSnapshots[0]->id);
        $this->assertSame($snapshot->id, $answer->answerParts[1]->evidenceSnapshots[0]->id);
        $this->assertNotSame('ev-01', $answer->answerParts[0]->public_id);
        DB::table('document_chunks')->where('id', $chunk->id)->update([
            'text' => 'Later source state must not rewrite the citation snapshot.',
        ]);
        $this->assertSame($chunk->text, $snapshot->fresh()->cited_text_verbatim);
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
