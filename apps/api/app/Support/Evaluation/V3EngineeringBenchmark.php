<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class V3EngineeringBenchmark
{
    public const ID = 'dolved-care-engineering';

    public const VERSION = '3';

    public const POPULATION_ID = 'dolved-care-engineering-v3-engineering-v1';

    public const POPULATION_DIGEST = 'faac5aa922671d13402fefc75b0c1e613f9edd8fc90bf7e9812b4bf3d14f5d6a';

    public const BENCHMARK_AUTHORING_DIGEST = 'eea41b2efb7b5d84c130365b9dc13fdd24ab93696f5ecb5e990c25930a233b03';

    public const PROVISIONING_DEFINITION_DIGEST = '125b30ec435ba1ba530d1d35778656023bdf2d601dae82200e41ce4078e22a43';

    public const EXPECTED_CASES = 10;

    public const EXPECTED_VARIANTS = 31;

    public const EXPECTED_FAMILIES = 72;

    public const EXPECTED_VERSIONS = 94;

    public const WORKSPACE_SLUG = 'evaluation-dolved-care-engineering-v3';

    public const WORKSPACE_NAME = 'Dolved Care Engineering Benchmark V3';

    public const OWNER_EMAIL = 'evaluation-v3@dolved.invalid';

    public const OWNER_NAME = 'Dolved V3 Engineering Evaluation';

    public const STATE_PATH = 'evaluation/dolved-care-engineering/v3/provisioning.json';

    public const ROOT = '/evaluation/engineering';

    public static function root(): string
    {
        return (string) env('V3_ENGINEERING_ROOT', self::ROOT);
    }

    private function __construct() {}
}
