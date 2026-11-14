<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\GenerationSide;
use App\Exceptions\GenerationException;
use App\Services\Generation\ValidateTemporalAnswerUse;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationEvidence;
use App\Support\Generation\GenerationRequest;
use App\Support\Retrieval\ExplicitTemporalReferences;
use Tests\TestCase;

final class TemporalAnswerUseTest extends TestCase
{
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
