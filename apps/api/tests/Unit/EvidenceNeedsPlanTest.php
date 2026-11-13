<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Retrieval\EvidenceNeedsPlan;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EvidenceNeedsPlanTest extends TestCase
{
    public function test_extractive_execution_discards_every_diagnostic_aspect_and_keeps_grounded_context(): void
    {
        $need = ['need_id' => 'n1', 'aspect' => 'Invented orbital insurance examples',
            'central' => true, 'question_span' => ['turn_id' => 'u2', 'quote' => 'Who maintains it?'],
            'context_spans' => [['turn_id' => 'u1', 'quote' => 'equipment register']],
            'operation' => 'retrieve', 'authority' => ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null],
            'fact_reference' => null, 'applicability' => ['kind' => 'generic', 'source' => null],
            'claim_under_test' => null, 'scope_question' => null, 'supports_need_id' => null];
        $value = ['schema_version' => 'evidence-needs-plan-v4', 'decision' => 'plan', 'clarification' => null,
            'needs' => [$need], 'bindings' => []];
        $turns = [['turn_id' => 'u1', 'text' => 'Tell me about the equipment register.'], ['turn_id' => 'u2', 'text' => 'Who maintains it?']];
        $lineage = ['provider' => 'openai', 'model' => 'gpt-5-mini', 'contract_schema_version' => 'evidence-needs-plan-v4',
            'prompt_version' => 'bounded-evidence-needs-v26', 'adapter_version' => 'structured-chat-needs-v10'];
        ksort($lineage);
        $lineage['fingerprint'] = hash('sha256', json_encode($lineage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $plan = EvidenceNeedsPlan::fromArray($value, $turns, $lineage,
            ['stage' => 'planner', 'provider' => 'openai', 'model' => 'gpt-5-mini', 'execution' => 'provider_api',
                'request_count' => 1, 'retry_count' => 0, 'latency_ms' => 1, 'cost_basis' => 'unavailable']);
        $this->assertSame('equipment register Who maintains it?', $plan->retrievalPlan('n1')->query);
        $this->assertSame($plan->retrievalPlan('n1')->query, $plan->executionNeeds()[0]['aspect']);
        $this->assertSame($need['aspect'], $plan->value['needs'][0]['aspect']);
        $this->assertStringNotContainsString('orbital', json_encode($plan->retrievalPlan('n1')->evidenceNeed));
        $value['needs'][0]['context_spans'][0]['quote'] = 'fictional catalogue title';
        $this->expectException(InvalidArgumentException::class);
        EvidenceNeedsPlan::validate($value, $turns);
    }

    public function test_before_relation_cannot_be_relabelled_as_during_or_reversed(): void
    {
        $data = self::read('examples')[0];
        $question = 'Who handled it before July 2026?';
        $need = $data['plan']['needs'][1];
        $need['need_id'] = 'n1';
        $need['supports_need_id'] = null;
        $need['question_span'] = ['turn_id' => 'u1', 'quote' => $question];
        $need['authority'] = ['kind' => 'before', 'value' => '2026-07', 'precision' => 'month',
            'source' => ['turn_id' => 'u1', 'quote' => 'before July 2026']];
        $data['plan']['needs'] = [$need];
        $data['plan']['bindings'] = [];
        $turns = [['turn_id' => 'u1', 'text' => $question]];
        EvidenceNeedsPlan::validate($data['plan'], $turns);
        foreach (['period', 'after'] as $kind) {
            $data['plan']['needs'][0]['authority']['kind'] = $kind;
            try {
                EvidenceNeedsPlan::validate($data['plan'], $turns);
                $this->fail('A changed temporal relation was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Temporal relation is not grounded.', $exception->getMessage());
            }
        }
    }

    private static function read(string $name): array
    {
        return json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v3/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function examples(): iterable
    {
        foreach (self::read('examples') as $example) {
            yield $example['name'] => [$example];
        }
    }

    #[DataProvider('examples')]
    public function test_grounded_shared_examples(array $example): void
    {
        EvidenceNeedsPlan::validate($example['plan'], $example['turns']);
        $this->addToAssertionCount(1);
    }

    public static function mutations(): iterable
    {
        foreach (self::read('invalid-mutations') as $mutation) {
            yield $mutation['name'] => [$mutation];
        }
    }

    #[DataProvider('mutations')]
    public function test_shared_invalid_plans_fail_closed(array $mutation): void
    {
        $example = self::read('examples')[$mutation['example']];
        $node = &$example['plan'];
        foreach ($mutation['path'] as $key) {
            $node = &$node[$key];
        }
        $node = $mutation['value'];
        unset($node);
        $this->expectException(InvalidArgumentException::class);
        EvidenceNeedsPlan::validate($example['plan'], $example['turns']);
    }

    public function test_disputed_claim_is_grounded_and_independent_of_site_applicability(): void
    {
        $example = self::read('examples')[0];
        $question = 'At Riverside House, do formal grievances go to the Brambleford Office People team?';
        $example['turns'] = [['turn_id' => 'u1', 'text' => $question]];
        $example['plan']['needs'] = [array_replace($example['plan']['needs'][1], [
            'need_id' => 'n1',
            'aspect' => 'Where formal grievances are submitted at Riverside House',
            'question_span' => ['turn_id' => 'u1', 'quote' => $question],
            'supports_need_id' => null,
            'applicability' => ['kind' => 'named', 'source' => ['turn_id' => 'u1', 'quote' => 'Riverside House']],
            'claim_under_test' => ['version' => 'claim-under-test-v1', 'type' => 'named_recipient',
                'relation' => 'submission_destination',
                'source' => ['turn_id' => 'u1', 'quote' => 'Brambleford Office People team']],
        ])];
        $example['plan']['bindings'] = [];
        EvidenceNeedsPlan::validate($example['plan'], $example['turns']);
        $this->addToAssertionCount(1);

        $example['plan']['needs'][0]['applicability'] = ['kind' => 'generic', 'source' => null];
        EvidenceNeedsPlan::validate($example['plan'], $example['turns']);
        $this->addToAssertionCount(1);

        $example['plan']['needs'][0]['claim_under_test']['truth_value'] = true;
        try {
            EvidenceNeedsPlan::validate($example['plan'], $example['turns']);
            $this->fail('Planner-supplied truth value was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        unset($example['plan']['needs'][0]['claim_under_test']['truth_value']);

        foreach (['Brambleford Office People team altered', 'ev-99'] as $unquoted) {
            $example['plan']['needs'][0]['claim_under_test']['source']['quote'] = $unquoted;
            try {
                EvidenceNeedsPlan::validate($example['plan'], $example['turns']);
                $this->fail('Unquoted claim was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
