<?php

namespace Tests\Unit;

use App\Exceptions\GenerationException;
use App\Services\Generation\GenerationProfileFactory;
use App\Support\Retrieval\CurrentPolicyScope;
use App\Support\Retrieval\EvidenceNeedsPlan;
use InvalidArgumentException;
use Tests\TestCase;

class CurrentPolicyScopeTest extends TestCase
{
    public function test_python_produced_response_revalidates_and_projects_in_php(): void
    {
        $f = $this->fixture();
        $r = json_decode(file_get_contents('/contracts/http/current-policy-needs/v1/response.example.json'), true, flags: JSON_THROW_ON_ERROR);
        EvidenceNeedsPlan::validateWireShape($r, 'response');
        $plan = EvidenceNeedsPlan::fromArray($r['plan'], $f['turns'], $r['classifier_lineage'], $r['usage']);
        $this->assertSame('current', $plan->retrievalPlan('n1')->temporalMode->value);
        $this->assertSame('What protective gloves are required now', $plan->executionNeeds()[0]['aspect']);
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents('/contracts/http/current-policy-needs/v1/examples.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_relative_calendar_is_canonicalized_before_product_validation_without_changing_authority(): void
    {
        foreach ([['I will inspect equipment this year. Which current checks apply?', 'this year', 'year', '2026'],
            ['An incident happened last month. What is the reporting rule now?', 'last month', 'month', '2026-03']] as [$question, $quote, $precision, $expected]) {
            $r = json_decode(file_get_contents('/contracts/http/current-policy-needs/v1/response.example.json'), true, flags: JSON_THROW_ON_ERROR);
            $need = &$r['plan']['needs'][0];
            $need['question_span'] = ['turn_id' => 'u1', 'quote' => $question];
            $need['operation'] = 'retrieve';
            $need['authority'] = ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null];
            $need['fact_reference'] = ['value' => 'wrong model arithmetic', 'precision' => 'duration', 'source' => ['turn_id' => 'u1', 'quote' => $quote]];
            $need['requested_information'] = 'current_policy';
            $need['current_spans'] = $need['history_spans'] = [];
            $plan = EvidenceNeedsPlan::fromArray($r['plan'], [['turn_id' => 'u1', 'text' => $question]], $r['classifier_lineage'], $r['usage'], '2026-03-31T23:30:00Z');
            $this->assertSame($expected, $plan->value['needs'][0]['fact_reference']['value']);
            $this->assertSame($precision, $plan->value['needs'][0]['fact_reference']['precision']);
            $this->assertSame('current', $plan->retrievalPlan('n1')->temporalMode->value);
            $this->assertSame($question, $plan->value['needs'][0]['question_span']['quote']);
        }
    }

    public function test_multiple_unhandled_parts_use_grounded_base_spans_including_dependents(): void
    {
        $f = $this->fixture();
        $base = $f['plan']['needs'][0];
        $base['requested_information'] = null;
        $base['current_spans'] = $base['history_spans'] = [];
        $base['scope_error'] = 'invalid_scope';
        $f['plan']['needs'] = [$base];
        $dependent = $base;
        $dependent['need_id'] = 'n2';
        $dependent['supports_need_id'] = 'n1';
        $dependent['scope_error'] = 'dependent_scope';
        $dependent['question_span'] = ['turn_id' => 'u2', 'quote' => 'And who approves that choice?'];
        $dependent['authority'] = ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null];
        $dependent['operation'] = 'retrieve';
        $f['plan']['needs'][] = $dependent;
        $f['plan']['bindings'][] = [...$f['plan']['bindings'][0], 'need_id' => 'n2'];
        $f['turns'][] = ['turn_id' => 'u2', 'text' => $dependent['question_span']['quote']];
        CurrentPolicyScope::validate($f['plan'], $f['turns'], null);
        $rows = CurrentPolicyScope::dispositions($f['plan']);
        $this->assertSame([$base['question_span']], $rows[0]['affected_spans']);
        $this->assertSame([$dependent['question_span']], $rows[1]['affected_spans']);
        $this->assertSame('dependent_scope', $rows[1]['reason']);
        $this->assertSame([], CurrentPolicyScope::executionNeeds($f['plan']));
    }

    public function test_mirrored_partition_executes_only_current_and_retains_decline(): void
    {
        $f = $this->fixture();
        CurrentPolicyScope::validate($f['plan'], $f['turns'], '2026-11-16T12:00:00Z');
        $needs = CurrentPolicyScope::executionNeeds($f['plan']);
        $this->assertSame('What protective gloves are required now', $needs[0]['aspect']);
        $this->assertSame('current', $needs[0]['authority']['kind']);
        $this->assertSame('partial_scope', CurrentPolicyScope::dispositions($f['plan'])[0]['status']);
        $this->assertSame('previous', $f['plan']['needs'][0]['authority']['kind']);
    }

    public function test_overlapping_partition_is_rejected(): void
    {
        $f = $this->fixture();
        $f['plan']['needs'][0]['history_spans'] = $f['plan']['needs'][0]['current_spans'];
        $this->expectException(InvalidArgumentException::class);
        CurrentPolicyScope::validate($f['plan'], $f['turns'], null);
    }

    public function test_history_and_ambiguity_never_become_no_evidence_or_execute(): void
    {
        foreach (['history' => 'scope_declined', 'unclear' => 'ambiguity'] as $kind => $status) {
            $f = $this->fixture();
            $f['plan']['needs'][0]['requested_information'] = $kind;
            $f['plan']['needs'][0]['current_spans'] = [];
            $f['plan']['needs'][0]['history_spans'] = [];
            CurrentPolicyScope::validate($f['plan'], $f['turns'], null);
            $this->assertSame([], CurrentPolicyScope::executionNeeds($f['plan']));
            $this->assertSame($status, CurrentPolicyScope::dispositions($f['plan'])[0]['status']);
            $this->assertSame([$f['plan']['needs'][0]['question_span']], CurrentPolicyScope::dispositions($f['plan'])[0]['affected_spans']);
        }
    }

    public function test_historical_authority_cannot_be_relabelled_current(): void
    {
        $f = $this->fixture();
        $f['plan']['needs'][0]['requested_information'] = 'current_policy';
        $f['plan']['needs'][0]['current_spans'] = [];
        $f['plan']['needs'][0]['history_spans'] = [];
        $this->expectException(InvalidArgumentException::class);
        CurrentPolicyScope::validate($f['plan'], $f['turns'], null);
    }

    public function test_quarantine_does_not_hide_broken_base_authority(): void
    {
        $f = $this->fixture();
        $f['plan']['needs'][0]['requested_information'] = null;
        $f['plan']['needs'][0]['current_spans'] = [];
        $f['plan']['needs'][0]['history_spans'] = [];
        $f['plan']['needs'][0]['scope_error'] = 'invalid_scope';
        unset($f['plan']['needs'][0]['authority']);
        $this->expectException(InvalidArgumentException::class);
        CurrentPolicyScope::validate($f['plan'], $f['turns'], null);
    }

    public function test_quarantined_annotation_never_executes(): void
    {
        $f = $this->fixture();
        $n = &$f['plan']['needs'][0];
        $n['requested_information'] = null;
        $n['current_spans'] = $n['history_spans'] = [];
        $n['scope_error'] = 'invalid_scope';
        CurrentPolicyScope::validate($f['plan'], $f['turns'], null);
        $this->assertSame([], CurrentPolicyScope::executionNeeds($f['plan']));
        $this->assertSame('interpretation_failure', CurrentPolicyScope::dispositions($f['plan'])[0]['status']);
    }

    public function test_profile_is_server_selected_default_off_and_pinned(): void
    {
        config(['conversation.needs_workspace_ids' => [], 'conversation.current_policy_workspace_ids' => []]);
        $factory = app(GenerationProfileFactory::class);
        $this->assertNull($factory->needsPin('workspace-one'));
        config(['conversation.current_policy_workspace_ids' => ['workspace-one']]);
        $pin = $factory->needsPin('workspace-one');
        $this->assertSame('current-policy-pool-v1', $pin['product_profile']);
        $this->assertNull($factory->needsPin('workspace-two'));
        $factory->validateCurrentPolicyPin($pin);
        $pin['candidate_k']++;
        $this->expectException(GenerationException::class);
        $factory->validateCurrentPolicyPin($pin);
    }
}
