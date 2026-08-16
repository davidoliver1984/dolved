<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class Exp0008EngineeringBenchmark
{
    public const ID = 'dolved-care-engineering';

    public const VERSION = '3';

    public const POPULATION_ID = 'dolved-care-engineering-v3-engineering-v1';

    public const POPULATION_DIGEST = 'd24d61a9aef55c8d3ca8d6609fbb44683665acc22e8d4f9652f00cb4d575d4c3';

    public const BENCHMARK_AUTHORING_DIGEST = '72eb717e8f0fd561022a252a80e56ce5b3e73106087b3b6e8772c56b9eef0df4';

    public const PROVISIONING_DEFINITION_DIGEST = '976bc85e0f3000a8e9e14b18e16f9a1a67aea3d7be92e25f58c871dbe32c637c';

    public const EXPECTED_CASES = 10;

    public const EXPECTED_VARIANTS = 31;

    public const WORKSPACE_SLUG = V3EngineeringBenchmark::WORKSPACE_SLUG;

    public const ROOT = V3EngineeringBenchmark::ROOT;

    public static function root(): string
    {
        return (string) env('V3_ENGINEERING_ROOT', self::ROOT);
    }

    private function __construct() {}
}
