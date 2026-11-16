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
