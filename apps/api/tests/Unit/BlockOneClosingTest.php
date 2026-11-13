<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Retrieval\TemporalPeriodResolver;
use App\Support\Retrieval\EvidenceNeedsPlan;
use App\Support\Retrieval\PlannerUsage;
use App\Support\Retrieval\RelativeCalendar;
use InvalidArgumentException;
use Tests\TestCase;

final class BlockOneClosingTest extends TestCase
{
    public function test_temporal_assessments_survive_cross_service_diagnostics_without_rewriting_authority(): void
    {
        $assessment = ['need_id' => 'n1', 'temporal_intent_preserved' => 'no',
            'references' => [['turn_id' => 'u1', 'quote' => 'in the previous summer', 'role' => 'authority', 'raw' => 'private']],
            'new_authority' => 'must not survive'];
        $check = ['version' => 'interpretation-check-v3', 'code' => 'meaning_changed', 'checker_code' => 'meaning_changed',
            'citation_verification' => 'verified', 'verification_reason' => null,
            'temporal_assessments' => [$assessment], 'input_tokens' => 123, 'output_tokens' => 45, 'latency_ms' => 67];
        $safe = PlannerUsage::safeAttempts([['status' => 'rejected', 'interpretation_check' => $check]])[0]['interpretation_check'];
        $this->assertSame('interpretation-check-v3', $safe['version']);
        $this->assertSame('meaning_changed', $safe['code']);
        $this->assertSame('verified', $safe['citation_verification']);
        $this->assertSame([['need_id' => 'n1', 'temporal_intent_preserved' => 'no',
            'references' => [['turn_id' => 'u1', 'quote' => 'in the previous summer', 'role' => 'authority']]]], $safe['temporal_assessments']);
        $this->assertSame(123, $safe['input_tokens']);
        $this->assertSame(45, $safe['output_tokens']);
        $check['temporal_assessments'] = [['need_id' => 'n1', 'temporal_intent_preserved' => 'invented']];
        $safe = PlannerUsage::safeAttempts([['interpretation_check' => $check]])[0]['interpretation_check'];
        $this->assertSame([], $safe['temporal_assessments']);
    }

    public function test_citation_diagnostics_preserve_verification_separately_from_judgement(): void
    {
        foreach (['missing_citation', 'user_quote_not_found', 'inspected_needs_mismatch', 'unknown_need', 'plan_quote_not_found', 'plan_quote_in_user_turns', 'application_grounded_period'] as $reason) {
            $check = ['version' => 'interpretation-check-v2', 'code' => 'accepted', 'checker_code' => 'scope_added',
                'citation_verification' => 'unverifiable', 'verification_reason' => $reason,
                'citation' => ['need_id' => 'n1', 'plan_field' => 'aspect', 'plan_quote' => 'for agency staff',
                    'user_turn_id' => null, 'user_quote' => null, 'inspected_need_ids' => [], 'raw_response' => 'private'],
                'input_tokens' => 42, 'output_tokens' => 7, 'latency_ms' => 12];
            $safe = PlannerUsage::safeAttempts([['status' => 'accepted', 'interpretation_check' => $check]])[0]['interpretation_check'];
            $this->assertSame('accepted', $safe['code']);
            $this->assertSame('scope_added', $safe['checker_code']);
            $this->assertSame('unverifiable', $safe['citation_verification']);
            $this->assertSame($reason, $safe['verification_reason']);
            $this->assertSame('for agency staff', $safe['citation']['plan_quote']);
            $this->assertArrayNotHasKey('raw_response', $safe['citation']);
            $this->assertSame(42, $safe['input_tokens']);
        }
        foreach (['clause_omitted', 'meaning_changed', 'scope_added'] as $code) {
            $check['code'] = $check['checker_code'] = $code;
            $check['citation_verification'] = 'verified';
            $check['verification_reason'] = null;
            $safe = PlannerUsage::safeAttempts([['status' => 'rejected', 'interpretation_check' => $check]])[0]['interpretation_check'];
            $this->assertSame($code, $safe['checker_code']);
            $this->assertSame('verified', $safe['citation_verification']);
            $this->assertNull($safe['verification_reason']);
        }
    }

    public function test_application_owns_relative_arithmetic_without_changing_quotes_or_intent(): void
    {
        foreach ([['this year', '2026', 'year'], ['last year', '2025', 'year'],
            ['this quarter', '2026-01-01/2026-03-31', 'interval'],
            ['Please check last month for me.', '2025-12', 'month']] as [$quote, $expected, $precision]) {
            foreach (['authority', 'fact_reference'] as $field) {
                $reference = ['source' => ['turn_id' => 'u1', 'quote' => $quote], 'value' => '1900', 'precision' => 'year'];
                if ($field === 'authority') {
                    $reference['kind'] = 'period';
                }
                $plan = ['needs' => [[$field => $reference]]];
                $normal = RelativeCalendar::canonicalize($plan, [['turn_id' => 'u1', 'text' => $quote]], '2026-01-01T00:00:00Z');
                $this->assertSame(array_replace($reference, ['value' => $expected, 'precision' => $precision]), $normal['needs'][0][$field]);
            }
        }
        $this->assertNull(RelativeCalendar::period('and the month before?', '2026-01-01T00:00:00Z'));
    }

    public function test_relative_quotes_must_be_grounded_and_single(): void
    {
        foreach (['last month and this year', 'last month then last month', 'invented last year'] as $quote) {
            try {
                RelativeCalendar::canonicalize(['needs' => [['authority' => ['source' => ['turn_id' => 'u1', 'quote' => $quote]]]]],
                    [['turn_id' => 'u1', 'text' => 'last month and this year; last month then last month']], '2026-01-01T00:00:00Z');
                $this->fail('Invalid reference must not be canonicalized.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_shared_relative_calendar_rules_and_timezone_boundaries(): void
    {
        $cases = json_decode(file_get_contents('/contracts/http/retrieval-needs/v3/relative-calendar-examples.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            $period = RelativeCalendar::period($case['quote'], $case['at']);
            $this->assertSame($case['precision'], $period['precision']);
            $this->assertSame($case['value'], $period['value']);
            $this->assertSame($case['start'], $period['start_inclusive']);
            $this->assertSame($case['end'], $period['end_exclusive']);
            [$start, $end] = app(TemporalPeriodResolver::class)->window($case['value'], $case['quote']);
            $this->assertSame($case['start'], $start->toIso8601String());
            $this->assertSame($case['end'], $end->toIso8601String());
        }
        $this->assertNull(RelativeCalendar::period('when I did that shift', '2026-11-12T12:00:00Z'));
    }

    public function test_relative_fact_date_is_revalidated_against_followup_anchor(): void
    {
        $examples = json_decode(file_get_contents('/contracts/http/retrieval-needs/v3/examples.json'), true, flags: JSON_THROW_ON_ERROR);
        $plan = $examples[0]['plan'];
        $need = $plan['needs'][1];
        $need['need_id'] = 'n1';
        $need['supports_need_id'] = null;
        $need['question_span'] = ['turn_id' => 'u2', 'quote' => 'When is it paid?'];
        $need['authority'] = ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null];
        $need['operation'] = 'retrieve';
        $need['fact_reference'] = ['value' => '2026-02', 'precision' => 'month', 'source' => ['turn_id' => 'u1', 'quote' => 'last month']];
        $plan['needs'] = [$need];
        $plan['bindings'] = [array_replace($plan['bindings'][1], ['need_id' => 'n1'])];
        $turns = [['turn_id' => 'u1', 'text' => 'My claim was approved last month.'], ['turn_id' => 'u2', 'text' => 'When is it paid?']];
        EvidenceNeedsPlan::validate($plan, $turns, '2026-03-01T00:00:00Z');
        $lineage = ['provider' => 'openai', 'model' => 'gpt-5-mini', 'contract_schema_version' => 'evidence-needs-plan-v3',
            'prompt_version' => 'bounded-evidence-needs-v25', 'adapter_version' => 'structured-chat-needs-v9'];
        ksort($lineage);
        $lineage['fingerprint'] = hash('sha256', json_encode($lineage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $result = EvidenceNeedsPlan::fromArray($plan, $turns, $lineage,
            ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini', 'execution' => 'provider_api',
                'request_count' => 1, 'retry_count' => 0, 'latency_ms' => 1, 'cost_basis' => 'unavailable'], '2026-04-01T00:00:00Z');
        $this->assertSame('2026-03', $result->retrievalPlan('n1')->factDate);
        $this->expectException(InvalidArgumentException::class);
        EvidenceNeedsPlan::validate($plan, $turns, '2026-04-01T00:00:00Z');
    }

    public function test_interpretation_codes_and_receipts_survive_without_prose(): void
    {
        foreach (['clause_omitted', 'meaning_changed', 'scope_added', 'interpretation_check_unavailable'] as $code) {
            $validation = ['stage' => 'interpretation', 'field' => 'root', 'code' => $code];
            $receipt = PlannerUsage::safeAttempts([['validation' => $validation, 'status' => 'rejected',
                'interpretation_check' => ['version' => 'interpretation-check-v1', 'code' => 'clause_omitted',
                    'input_tokens' => 123, 'output_tokens' => 45, 'latency_ms' => 67, 'raw_output' => 'not retained']]])[0];
            $this->assertSame($validation, $receipt['validation']);
            $this->assertSame(123, $receipt['interpretation_check']['input_tokens']);
            $this->assertSame(67, $receipt['interpretation_check']['latency_ms']);
            $this->assertArrayNotHasKey('raw_output', $receipt['interpretation_check']);
        }
    }
}
