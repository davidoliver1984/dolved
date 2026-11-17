<?php

namespace Tests\Unit;

use App\Support\Retrieval\CurrentPolicyScope;
use App\Support\Retrieval\EvidenceNeedsPlan;
use InvalidArgumentException;
use Tests\TestCase;

class ScopeNormalisationProducerTest extends TestCase
{
    private function rows(): array
    {
        return json_decode(file_get_contents('/contracts/http/current-policy-needs/v1/normalised-producer.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function consume(array $r): EvidenceNeedsPlan
    {
        return EvidenceNeedsPlan::fromArray($r['plan'], $r['turns'], $r['classifier_lineage'], $r['usage'], '2026-11-17T12:00:00Z');
    }

    public function test_real_python_adapter_outputs_revalidate_without_consumer_normalisation(): void
    {
        $expected = ['current_policy' => ['admitted'], 'history' => ['scope_declined'], 'separate' => ['scope_declined', 'admitted'], 'mixed' => ['partial_scope']];
        foreach ($this->rows() as $name => $row) {
            $plan = $this->consume($row);
            $this->assertSame($row['plan'], $plan->value);
            $this->assertSame($expected[$name], array_column(CurrentPolicyScope::dispositions($plan->value), 'status'));
            foreach ($plan->executionNeeds() as $need) {
                $this->assertSame('current', $need['authority']['kind']);
            }
        }
    }

    public function test_consumer_still_rejects_bad_scope_authority_grounding_and_identity(): void
    {
        foreach (['wrong_side', 'redundant_not_canonical', 'malformed', 'authority', 'grounding', 'applicability', 'dependency', 'identity'] as $mode) {
            $r = $this->rows()['current_policy'];
            $n = &$r['plan']['needs'][0];
            match ($mode) {
                'wrong_side' => $n['history_spans'] = [$n['question_span']],
                'redundant_not_canonical' => $n['current_spans'] = [$n['question_span']],
                'malformed' => $n['current_spans'] = 'invalid',
                'authority' => $n['authority'] = ['kind' => 'previous', 'value' => 'inspection register', 'precision' => 'named', 'source' => ['turn_id' => 'u1', 'quote' => 'inspection register']],
                'grounding' => $n['question_span']['quote'] = 'not in this question',
                'applicability' => $n['applicability'] = ['kind' => 'named', 'source' => ['turn_id' => 'u1', 'quote' => 'another site']],
                'dependency' => $n['supports_need_id'] = 'n9',
                'identity' => $r['classifier_lineage']['adapter_version'] = 'structured-current-policy-v1',
            };
            unset($n);
            if ($mode === 'identity') {
                $parts = $r['classifier_lineage'];
                unset($parts['fingerprint']);
                ksort($parts);
                $r['classifier_lineage']['fingerprint'] = hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            }
            try {
                $this->consume($r);
                $this->fail('Invalid producer output accepted: '.$mode);
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }
}
