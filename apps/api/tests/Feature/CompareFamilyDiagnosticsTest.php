<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Evaluation\RunR28ProductionPathDiagnostic;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

final class CompareFamilyDiagnosticsTest extends TestCase
{
    #[DataProvider('decisions')]
    public function test_consumer_and_exporter_preserve_diagnostics_not_execution(string $original, string $effective, ?string $code): void
    {
        $family = (string) Str::uuid();
        $chunk = (string) Str::uuid();
        $candidate = ['document_family_id' => $family, 'chunk_id' => $chunk,
            'document_family_title' => 'Clothing Guidance', 'chunk_text' => 'Religious and cultural dress accommodation is permitted.'];
        $workspace = (new Workspace)->forceFill(['public_id' => (string) Str::uuid()]);
        $withDiagnostics = true;
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($family, $chunk, $original, $effective, $code, &$withDiagnostics) {
            $this->assertSame(['retrieval.compare-family'], $request->header('X-Retrieval-Caller-Purpose'));
            $this->assertCount(12, $request['candidates']);

            return Http::response(['contract_version' => 1, 'request_id' => $request['request_id'],
                'selection' => ['decision' => $effective, 'family_id' => $effective === 'selected' ? $family : null,
                    'chunk_id' => $effective === 'selected' ? $chunk : null,
                    'quote' => $effective === 'selected' ? 'Religious and cultural dress accommodation' : null],
                ...($withDiagnostics ? ['original_decision' => $original, 'validation_rejection_code' => $code,
                    'candidate_identities' => [['family_id' => 'untrusted', 'chunk_id' => 'untrusted', 'text' => 'not logged']]] : []),
                'usage' => ['stage' => 'compare_family_selection', 'provider' => 'openai', 'model' => 'test-model',
                    'execution' => 'provider_api', 'request_count' => 1, 'retry_count' => 0, 'latency_ms' => 1, 'cost_basis' => 'unavailable']]);
        });
        $candidates = [$candidate];
        for ($i = 1; $i < 13; $i++) {
            $candidates[] = [...$candidate, 'chunk_id' => (string) Str::uuid()];
        }
        $client = app(RetrievalClient::class);
        $result = $client->selectTopicalCompareFamily($workspace, 'How did the clothing rule change?', $candidates);
        $withDiagnostics = false;
        $legacy = $client->selectTopicalCompareFamily($workspace, 'How did the clothing rule change?', $candidates);
        foreach (['decision', 'family_id', 'chunk_id', 'usage'] as $key) {
            $this->assertSame($legacy[$key], $result[$key]);
        }
        $this->assertNull($legacy['original_decision']);
        $this->assertSame($original, $result['original_decision']);
        $this->assertSame($code, $result['validation_rejection_code']);
        $this->assertSame($effective, $result['decision']);
        $expectedIds = array_map(fn ($item) => ['family_id' => $item['document_family_id'], 'chunk_id' => $item['chunk_id']], array_slice($candidates, 0, 12));
        $this->assertSame($expectedIds, $result['candidate_identities']);
        $export = new ReflectionMethod(RunR28ProductionPathDiagnostic::class, 'boundedStage');
        $event = $export->invoke(app(RunR28ProductionPathDiagnostic::class), 'need.n1.topical_family_selection', $result);
        $this->assertSame($original, $event['original_decision']);
        $this->assertSame($code, $event['validation_rejection_code']);
        $this->assertSame($effective, $event['decision']);
        $this->assertSame($expectedIds, $event['candidate_identities']);
        $this->assertStringNotContainsString('Religious', json_encode($event));
        $this->assertStringNotContainsString('untrusted', json_encode($event));
        Http::assertSentCount(2);
    }

    public static function decisions(): array
    {
        return [
            'genuine no match' => ['no_match', 'no_match', null],
            'genuine ambiguous' => ['ambiguous', 'ambiguous', null],
            'valid selection' => ['selected', 'selected', null],
            'identity rejected' => ['selected', 'no_match', 'identity_mismatch'],
            'quotation rejected' => ['selected', 'no_match', 'quote_not_in_candidate'],
            'quotation length rejected' => ['selected', 'no_match', 'quote_length'],
        ];
    }
}
