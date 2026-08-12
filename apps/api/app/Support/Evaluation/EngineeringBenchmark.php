<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class EngineeringBenchmark
{
    public const ID = 'dolved-care-engineering';

    public const VERSION = 'v2';

    public const WORKSPACE_NAME = 'Alderbridge Care Engineering Benchmark';

    public const WORKSPACE_SLUG = 'evaluation-dolved-care-engineering-v2';

    public const OWNER_EMAIL = 'benchmark@dolved.invalid';

    public const OWNER_NAME = 'Alderbridge Benchmark Runner';

    public const ROOT = '/evaluation/benchmarks/dolved-care-engineering/v2';

    public const STATE_PATH = 'evaluation/dolved-care-engineering/v2/provisioning.json';

    public const EXPECTED_FAMILIES = 71;

    public const EXPECTED_VERSIONS = 93;

    public const EXPECTED_ENGINEERING_CASES = 42;

    public const EXPECTED_ENGINEERING_VARIANTS = 126;

    private function __construct() {}
}
