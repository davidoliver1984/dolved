<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\CoverageSelectionFailure;
use App\Exceptions\RetrievalException;
use App\Support\Retrieval\CoverageAllocation;
use PHPUnit\Framework\TestCase;

final class CoverageAllocationTest extends TestCase
{
    public function test_malformed_output_is_typed_and_never_consumed(): void
    {
        foreach ([null, ['aspects' => ['bad']], ['aspects' => [['need_id' => []]]]] as $proposal) {
            try {
                (new CoverageAllocation)->allocate([], [], $proposal);
                $this->fail('Malformed proposal must be rejected.');
            } catch (CoverageSelectionFailure $failure) {
                $this->assertFalse($failure->runStopRequired);
                $this->assertStringStartsWith('coverage_', $failure->reason);
            }
        }
    }

    public function test_saturation_is_unmet_not_claimed_coverage_and_shared_passages_use_one_slot(): void
    {
        $needs = [['need_id' => 'n1', 'question_span' => 'red blue green white black purple', 'operation' => 'retrieve']];
        $candidates = $aspects = [];
        foreach (explode(' ', $needs[0]['question_span']) as $index => $word) {
            $candidates[] = ['candidate_id' => $word, 'text' => $word.' rule', 'associations' => ['n1:primary'], 'rank' => $index + 1];
            $aspects[] = ['need_id' => 'n1', 'quote' => $word, 'support' => [['candidate_id' => $word,
                'association' => 'n1:primary', 'quote' => $word.' rule']], 'unmet' => 'none'];
        }
        $service = new CoverageAllocation;
        $result = $service->allocate($needs, $candidates, ['aspects' => $aspects]);
        $this->assertCount(5, $result['selected']);
        $this->assertFalse($result['semantic_completeness_verified']);
        $this->assertSame('capacity', $result['aspects'][5]['unmet']);
        $candidates[0]['text'] .= ' purple rule';
        $aspects[5]['support'][0]['candidate_id'] = 'red';
        $result = $service->allocate($needs, $candidates, ['aspects' => $aspects]);
        $this->assertCount(5, $result['selected']);
        $this->assertSame(array_fill(0, 6, true), array_column($result['aspects'], 'allocated'));
    }

    public function test_a_missing_requested_need_is_rejected(): void
    {
        $this->expectException(RetrievalException::class);
        (new CoverageAllocation)->allocate([['need_id' => 'n1', 'question_span' => 'Who?', 'operation' => 'retrieve']], [], ['aspects' => []]);
    }

    public function test_proposed_unmet_support_is_not_an_answerability_gate(): void
    {
        $service = new CoverageAllocation;
        $needs = [['need_id' => 'n1', 'operation' => 'retrieve', 'question_span' => 'Who checks?']];
        $qualified = [['candidate_id' => 'passage', 'text' => 'The engineer checks.', 'rank' => 1, 'associations' => ['n1:primary']]];
        $allocated = $service->allocate($needs, $qualified, ['aspects' => [[
            'need_id' => 'n1', 'quote' => 'Who checks?', 'support' => [], 'unmet' => 'no_support']]]);
        $selected = $service->fillFromExisting($allocated['selected'], [['chunk_id' => 'passage', 'need_id' => 'n1', 'side' => 'primary']], $needs, $qualified);
        $this->assertSame([['candidate_id' => 'passage', 'associations' => ['n1:primary']]], $selected);
        $this->assertSame('no_support', $allocated['aspects'][0]['unmet']);
    }
}
