<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class V3EngineeringBenchmark
{
    public const ID = 'dolved-care-engineering';

    public const VERSION = '3';

    public const POPULATION_ID = 'dolved-care-engineering-v3-engineering-v1';

    public const POPULATION_DIGEST = '73c53fe6602a807fa12b022f8656f775af02cc1b83faed6aeb93fc9d9b424797';

    public const BENCHMARK_AUTHORING_DIGEST = 'e87bd8c782671b4e83aa50897d2c4f67578689f0a786f94fa21f236b38bde4f3';

    public const PROVISIONING_DEFINITION_DIGEST = 'aedb24a2cf8bc9bc664ea684cf38f3b712b9c9338453e852b774df0f829e949c';

    public const EXPECTED_CASES = 8;

    public const EXPECTED_VARIANTS = 24;

    public const EXPECTED_FAMILIES = 71;

    public const EXPECTED_VERSIONS = 93;

    public const WORKSPACE_SLUG = 'evaluation-dolved-care-engineering-v3';

    public const ROOT = '/evaluation/engineering';

    public static function root(): string
    {
        return (string) env('V3_ENGINEERING_ROOT', self::ROOT);
    }

    private function __construct() {}
}
