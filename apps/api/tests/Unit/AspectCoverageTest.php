<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\GenerationOutcome;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\AspectCoverage;
use App\Support\Generation\GenerationResult;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AspectCoverageTest extends TestCase
{
    public static function cases(): iterable
    {
        $fixture = json_decode((string) file_get_contents('/contracts/fixtures/generation-aspect-coverage/v1.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($fixture['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    #[DataProvider('cases')]
    public function test_shared_aspect_contract(array $case): void
    {
        $raw = $case['result'];
        $result = new GenerationResult(
            GenerationOutcome::from($raw['outcome']),
            array_map(fn (array $part): AnswerPartResult => new AnswerPartResult($part['text'], $part['evidence_ids']), $raw['answer_parts']),
            $raw['unsupported_aspects'],
            $raw['insufficiency_reason'],
        );
        if (! $case['valid']) {
            $this->expectException(InvalidArgumentException::class);
        }
        $actual = AspectCoverage::derive($result, $case['need_ids'], $case['links']);
        if ($case['valid']) {
            $this->assertSame($case['expected_statuses'], $actual);
        }
    }
}
