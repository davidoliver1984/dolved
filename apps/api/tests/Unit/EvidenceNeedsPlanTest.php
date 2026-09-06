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
        return json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v2/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
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
}
