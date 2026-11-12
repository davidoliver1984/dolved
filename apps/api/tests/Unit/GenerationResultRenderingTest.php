<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\GenerationOutcome;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GenerationResultRenderingTest extends TestCase
{
    public function test_budget_limited_gap_discloses_selection_not_source_absence(): void
    {
        $result = new GenerationResult(GenerationOutcome::Qualified,
            [new AnswerPartResult('Confirmed guidance.', [])], ['The documents do not say the frequency.', 'Unrelated gap.'], null,
            aspectLinks: [
                ['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => [0]],
                ['need_id' => 'n2', 'answer_part_indices' => [], 'unsupported_aspect_indices' => [1]],
            ]);
        $updated = $result->withSelectionCoverage([
            ['need_id' => 'n1', 'aspect' => 'How frequently is monitoring done?', 'selection_coverage' => ['budget_cut_unit_count' => 2]],
            ['need_id' => 'n2', 'aspect' => 'Other question', 'selection_coverage' => ['budget_cut_unit_count' => 0]],
        ]);
        $this->assertStringContainsString('Additional eligible evidence was omitted', $updated->renderedText());
        $this->assertStringNotContainsString('The documents do not say', $updated->renderedText());
        $this->assertSame('Unrelated gap.', $updated->unsupportedAspects[1]);
        $this->assertSame($result->answerParts, $updated->answerParts);
        $this->assertSame($result->aspectLinks, $updated->aspectLinks);
        $this->assertSame($result->unsupportedAspects, $result->withSelectionCoverage([])->unsupportedAspects);
    }

    public static function qualifiedGapsFromR07(): array
    {
        return [
            '93 August closure' => ['Central Support Office closed on 4 May.', 'Whether it closed on 31 August 2026 is not established.'],
            '102 monitoring frequency' => ['The alarm monitoring company monitored the system.', 'The pilot monitoring frequency is not established.'],
            '122 first staff group' => ['The supervision frequencies are unchanged.', 'Which staff group is listed first is not established.'],
            '132 Finance responsibility' => ['The current multi-agency route is recorded.', 'The prior initial contact and Finance Manager responsibility are not established.'],
        ];
    }

    #[DataProvider('qualifiedGapsFromR07')]
    public function test_qualified_answer_displays_each_requested_gap(string $answer, string $gap): void
    {
        $result = new GenerationResult(
            GenerationOutcome::Qualified,
            [new AnswerPartResult($answer, ['ev-01'])],
            [$gap],
            null,
        );

        $this->assertSame(
            $answer."\n\nUnconfirmed requested aspects\n\n- ".$gap,
            $result->renderedText(),
        );
    }

    public function test_answered_result_does_not_gain_a_gap_section(): void
    {
        $result = new GenerationResult(
            GenerationOutcome::Answered,
            [new AnswerPartResult('The supported answer.', ['ev-01'])],
            [],
            null,
        );

        $this->assertSame('The supported answer.', $result->renderedText());
    }

    public function test_qualified_binary_answer_cannot_contradict_its_own_unsupported_aspect(): void
    {
        $gap = 'Whether a regular staff shuttle operates is not established by the supplied documents.';
        $result = new GenerationResult(
            GenerationOutcome::Qualified,
            [new AnswerPartResult('No — the supplied documents do not describe a staff shuttle.', ['ev-01'])],
            [$gap],
            null,
            aspectLinks: [[
                'need_id' => 'n1',
                'answer_part_indices' => [0],
                'unsupported_aspect_indices' => [0],
            ]],
            aspectResults: ['n1' => 'partial'],
        );

        $this->assertSame("Unconfirmed requested aspects\n\n- ".$gap, $result->renderedText());
    }

    public function test_binary_part_for_separately_supported_need_is_preserved(): void
    {
        $result = new GenerationResult(
            GenerationOutcome::Qualified,
            [new AnswerPartResult('No — the current policy excludes that exception.', ['ev-01'])],
            ['The separate historical rule is not established.'],
            null,
            aspectLinks: [
                ['need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => []],
                ['need_id' => 'n2', 'answer_part_indices' => [], 'unsupported_aspect_indices' => [0]],
            ],
            aspectResults: ['n1' => 'supported', 'n2' => 'unsupported'],
        );

        $this->assertStringStartsWith('No — the current policy excludes that exception.', $result->renderedText());
    }

    public function test_non_binary_partial_guidance_is_preserved(): void
    {
        $result = new GenerationResult(
            GenerationOutcome::Qualified,
            [new AnswerPartResult('The policy describes pool cars for community visits.', ['ev-01'])],
            ['Whether a staff shuttle operates is not established.'],
            null,
            aspectLinks: [[
                'need_id' => 'n1', 'answer_part_indices' => [0], 'unsupported_aspect_indices' => [0],
            ]],
            aspectResults: ['n1' => 'partial'],
        );

        $this->assertStringStartsWith('The policy describes pool cars for community visits.', $result->renderedText());
    }
}
