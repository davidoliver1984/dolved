<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Retrieval\EvidenceNeedsPlan;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EvidenceNeedsPlanTest extends TestCase
{
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
