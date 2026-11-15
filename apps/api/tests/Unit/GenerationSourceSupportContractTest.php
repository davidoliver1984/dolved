<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\GenerationSide;
use App\Services\Generation\GenerationProfileFactory;
use App\Services\Generation\ValidateGenerationResult;
use App\Support\Generation\GenerationEvidence;
use App\Support\Generation\GenerationRequest;
use Tests\TestCase;

final class GenerationSourceSupportContractTest extends TestCase
{
    public function test_current_consumer_accepts_real_python_adapter_projection_and_identity(): void
    {
        $fixture = json_decode(file_get_contents('/contracts/testing/generation-source-support-v1.json'), true, flags: JSON_THROW_ON_ERROR);
        $profile = app(GenerationProfileFactory::class)->configuredNeeds();
        $this->assertSame($profile->contractVersion, $fixture['profile']['contract_version']);
        $this->assertSame($profile->promptVersion, $fixture['profile']['prompt_version']);
        $this->assertSame($profile->adapterVersion, $fixture['profile']['adapter_version']);
        $this->assertSame($profile->qualityAffectingConfiguration, $fixture['profile']['quality_affecting_configuration']);
        $raw = $fixture['request'];
        $evidence = array_map(fn ($item) => new GenerationEvidence(
            $item['evidence_id'], $item['text'], $item['document_chunk_id'], $item['document_id'],
            $item['ingestion_event_claim_id'], $item['source_provenance'], $item['temporal_authority'],
            $item['applicability_context'], GenerationSide::from($item['side']),
        ), $raw['evidence']);
        $request = new GenerationRequest($raw['request_id'], $raw['workspace_id'], $raw['question'], $evidence,
            $raw['constraints']['context_policy_version'], $raw['constraints']['max_context_characters'],
            array_map(GenerationSide::from(...), $raw['constraints']['required_sides']), evidenceNeeds: $raw['evidence_needs']);
        $result = app(ValidateGenerationResult::class)->handleNeeds($request, array_column($raw['evidence_needs'], 'need_id'), $fixture['result']);
        $this->assertCount(2, $result->answerParts);
        $this->assertSame('Synthetic answer.', $result->answerParts[0]->text);
        $this->assertSame(['ev-01', 'ev-02'], $result->answerParts[0]->evidenceIds);
    }
}
