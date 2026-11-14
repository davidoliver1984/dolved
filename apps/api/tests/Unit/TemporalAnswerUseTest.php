<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\GenerationSide;
use App\Exceptions\GenerationException;
use App\Models\AnswerPart;
use App\Models\GeneratedAnswer;
use App\Services\Generation\ValidateGenerationResult;
use App\Services\Generation\ValidateTemporalAnswerUse;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationEvidence;
use App\Support\Generation\GenerationRequest;
use App\Support\Retrieval\ExplicitTemporalReferences;
use Tests\TestCase;

final class TemporalAnswerUseTest extends TestCase
{
    public function test_shared_part_requires_each_independent_temporal_association(): void
    {
        foreach (['valid', 'borrowed', 'missing', 'duplicate', 'version', 'interval', 'quote', 'unassigned'] as $case) {
            $needs = $uses = $links = $evidence = [];
            foreach (['n1', 'n2'] as $index => $id) {
                $target = ['target_id' => $id.':requested', 'need_id' => $id, 'family_id' => 'family',
                    'purpose' => 'requested', 'mode' => 'current', 'at' => '2026-11-14T00:00:00Z', 'reference' => null,
                    'status' => 'searched', 'versions' => [['version_id' => 'version', 'start' => '2026-01-01T00:00:00Z', 'end' => null]]];
                $needs[] = ['need_id' => $id, 'question_span' => ['turn_id' => 'u1', 'quote' => 'What is required now?'],
                    'evidence_ids' => ['ev-'.$index], 'temporal_targets' => [$target]];
                $uses[] = ['part_index' => 0, 'need_id' => $id, 'target_id' => $target['target_id'], 'question_quote' => 'now', 'evidence_ids' => ['ev-'.$index]];
                $links[] = ['need_id' => $id, 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []];
                $evidence[] = new GenerationEvidence('ev-'.$index, 'Both duties require daily inspection.', 1, 1, 1, [],
                    ['need_id' => $id, 'document_family_id' => 'family', 'document_version_id' => 'version', 'temporal_target_ids' => [$target['target_id']]], [], GenerationSide::Primary);
            }
            if ($case === 'borrowed') {
                $uses[0]['evidence_ids'] = ['ev-1'];
            }
            if ($case === 'missing') {
                array_pop($uses);
            }
            if ($case === 'duplicate') {
                $uses[] = $uses[0];
            }
            if ($case === 'version') {
                $needs[1]['temporal_targets'][0]['versions'][0]['version_id'] = 'other';
            }
            if ($case === 'interval') {
                $needs[1]['temporal_targets'][0]['versions'][0]['end'] = '2026-02-01T00:00:00Z';
            }
            if ($case === 'quote') {
                $uses[1]['question_quote'] = 'invented';
            }
            if ($case === 'unassigned') {
                $uses[1]['evidence_ids'] = [];
            }
            $request = new GenerationRequest('request', 'workspace', 'What is required now?', $evidence, 'test', 8000,
                [GenerationSide::Primary], evidenceNeeds: $needs);
            $payload = ['contract_version' => 5, 'outcome' => 'answered',
                'answer_parts' => [['text' => 'Both duties require daily inspection.', 'evidence_ids' => ['ev-0', 'ev-1']]],
                'unsupported_aspects' => [], 'insufficiency_reason' => null, 'usage' => null,
                'aspect_links' => $links, 'entailment_checks' => [['part_index' => 0, 'verdict' => 'supported', 'missing_condition' => null]],
                'temporal_uses' => $uses];
            try {
                $result = (new ValidateGenerationResult)->handleNeeds($request, ['n1', 'n2'], $payload);
                $this->assertSame('valid', $case);
                $this->assertCount(2, $result->temporalContext['uses']);
                $this->assertSame('temporal-answer-use-v2', $result->temporalContext['version']);
                $this->assertSame(2, substr_count($result->renderedText(), 'Temporal basis:'));
                $reloaded = new GeneratedAnswer;
                $reloaded->forceFill(['outcome' => 'answered', 'answer_parts' => $payload['answer_parts'],
                    'unsupported_aspects' => [], 'aspect_links' => $links, 'temporal_context' => $result->temporalContext]);
                $reloaded->setRelation('answerParts', collect([new AnswerPart(['text' => $payload['answer_parts'][0]['text']])]));
                $this->assertSame($result->renderedText(), $reloaded->renderedText());
                $legacy = $result->temporalContext;
                $legacy['version'] = 'temporal-answer-use-v1';
                $legacy['uses'] = [$legacy['uses'][0]];
                unset($legacy['uses'][0]['evidence_ids']);
                $reloaded->temporal_context = $legacy;
                $this->assertSame(1, substr_count($reloaded->renderedText(), 'Temporal basis:'));
            } catch (GenerationException) {
                $this->assertNotSame('valid', $case);
            }
        }
    }

    public function test_calendar_inventory_preserves_precision_and_excludes_numeric_non_dates(): void
    {
        $question = 'On 29 February 2024, during March 2025 and in 2023. Compare 2024-12-31. '
            .'Ignore ID_2022, X2021, 2020.50, 19.2019, 2018/17, 2017-kg, 2016 years and 2015 hours; 2014,5; 3,2013; 2012%.';
        $refs = ExplicitTemporalReferences::forNeed(['question_span' => ['turn_id' => 'u7', 'quote' => $question]]);
        $this->assertSame(['29 February 2024', 'March 2025', '2023', '2024-12-31'], array_column($refs, 'quote'));
        $this->assertSame(['day', 'month', 'year', 'day'], array_column($refs, 'precision'));
        $this->assertSame(['u7'], array_values(array_unique(array_column($refs, 'turn_id'))));
        $this->assertNull($refs[1]['at']);
        $this->assertSame('2025-04-01T00:00:00+00:00', $refs[1]['period_end']);
        $this->assertSame('2024-01-01T00:00:00+00:00', $refs[2]['period_end']);
        $leap = ExplicitTemporalReferences::forNeed(['question_span' => ['turn_id' => 'u1', 'quote' => 'February 2024; December 2025; 2024']]);
        $this->assertSame('2024-03-01T00:00:00+00:00', $leap[0]['period_end']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $leap[1]['period_end']);
        $this->assertSame([], ExplicitTemporalReferences::forNeed(['question_span' => ['turn_id' => 'u1', 'quote' => '31 February 2024 or 2025-02-29']]));
        $this->assertSame([], ExplicitTemporalReferences::forNeed(['question_span' => ['turn_id' => 'u1', 'quote' => '9999 or December 9999']]));
    }

    public function test_inventory_is_independent_of_planner_labels_and_rejects_invalid_or_event_dates(): void
    {
        foreach (['current', 'date'] as $kind) {
            $need = ['authority' => ['kind' => $kind], 'question_span' => ['turn_id' => 'u1',
                'quote' => 'Compare 2024-02-29 with 12 August 2025; not 2025-02-29 or when I joined.'], 'context_spans' => []];
            $refs = ExplicitTemporalReferences::forNeed($need);
            $this->assertSame(['2024-02-29', '12 August 2025'], array_column($refs, 'quote'));
            $this->assertSame('2025-08-12T00:00:00+00:00', $refs[1]['at']);
        }
        $this->assertSame([], ExplicitTemporalReferences::forNeed(['question_span' => ['turn_id' => 'u1', 'quote' => 'When I joined, what applied?']]));
    }

    public function test_temporal_target_validation_rejects_cross_lane_family_version_and_quote_use(): void
    {
        $target = ['target_id' => 'n1:date:1', 'need_id' => 'n1', 'family_id' => 'family',
            'purpose' => 'precautionary', 'mode' => 'dated', 'at' => '2025-08-12T00:00:00+01:00',
            'reference' => ['turn_id' => 'u1', 'quote' => '12 August 2025'], 'status' => 'searched',
            'versions' => [['version_id' => 'old', 'start' => '2024-01-01T00:00:00Z', 'end' => '2026-01-01T00:00:00Z']]];
        $requested = [...$target, 'target_id' => 'n1:requested', 'mode' => 'current', 'purpose' => 'requested',
            'at' => '2026-11-14T00:00:00Z', 'reference' => null,
            'versions' => [['version_id' => 'current', 'start' => '2026-01-01T00:00:00Z', 'end' => null]]];
        $authority = ['need_id' => 'n1', 'document_family_id' => 'family', 'document_version_id' => 'old', 'temporal_target_ids' => ['n1:date:1']];
        $use = ['part_index' => 0, 'need_id' => 'n1', 'target_id' => 'n1:date:1', 'question_quote' => '12 August 2025'];
        foreach (['valid', 'valid_period', 'partial_period', 'invalid_period_bounds', 'current_from_history', 'family', 'version', 'lane', 'target', 'quote', 'interval', 'missing', 'other_need'] as $case) {
            $changedTarget = $target;
            $changedAuthority = $authority;
            $changedUse = $use;
            if (in_array($case, ['valid_period', 'partial_period', 'invalid_period_bounds'], true)) {
                $changedTarget['at'] = null;
                $changedTarget['reference'] = ['turn_id' => 'u1', 'quote' => 'August 2025', 'at' => null,
                    'precision' => 'month', 'value' => '2025-08', 'period_start' => '2025-08-01T00:00:00+00:00', 'period_end' => '2025-09-01T00:00:00+00:00'];
                $changedUse['question_quote'] = 'August 2025';
                if ($case === 'partial_period') {
                    $changedTarget['versions'][0]['end'] = '2025-08-15T00:00:00Z';
                } elseif ($case === 'invalid_period_bounds') {
                    $changedTarget['reference']['period_end'] = '2025-08-15T00:00:00+00:00';
                }
            }
            match ($case) {
                'current_from_history' => $changedUse['target_id'] = 'n1:requested',
                'family' => $changedAuthority['document_family_id'] = 'foreign',
                'version' => $changedAuthority['document_version_id'] = 'current',
                'lane' => $changedAuthority['temporal_target_ids'] = ['n1:requested'],
                'target' => $changedUse['target_id'] = 'invented',
                'quote' => $changedUse['question_quote'] = 'invented',
                'interval' => $changedTarget['versions'][0]['end'] = '2025-01-01T00:00:00Z',
                'other_need' => $changedUse['need_id'] = 'n2',
                default => null,
            };
            $request = new GenerationRequest('request', 'workspace', 'What applied on 12 August 2025?',
                [new GenerationEvidence('ev-01', 'Historical guidance.', 1, 1, 1, [], $changedAuthority, [], GenerationSide::Primary)],
                'test', 8000, [GenerationSide::Primary], evidenceNeeds: [['need_id' => 'n1',
                    'question_span' => ['turn_id' => 'u1', 'quote' => 'What applied on 12 August 2025?'],
                    'temporal_targets' => [$requested, $changedTarget]]]);
            try {
                $result = (new ValidateTemporalAnswerUse)->handle($request, [new AnswerPartResult('Historical answer.', ['ev-01'])],
                    [['need_id' => 'n1', 'answer_part_indices' => [0]]], $case === 'missing' ? [] : [$changedUse]);
                $this->assertContains($case, ['valid', 'valid_period']);
                $this->assertTrue($result['uses'][0]['planner_disagreement']);
            } catch (GenerationException) {
                $this->assertNotContains($case, ['valid', 'valid_period']);
            }
        }
    }
}
