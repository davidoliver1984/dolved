<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class EngineeringBenchmark
{
    public const ID = 'dolved-care-engineering';

    public const VERSION = 'v2';

    public const DIGEST = 'aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d';

    public const WORKSPACE_NAME = 'Alderbridge Care Engineering Benchmark';

    public const WORKSPACE_SLUG = 'evaluation-dolved-care-engineering-v2';

    public const OWNER_EMAIL = 'benchmark@dolved.invalid';

    public const OWNER_NAME = 'Alderbridge Benchmark Runner';

    public const ROOT = '/evaluation/benchmarks/dolved-care-engineering/v2';

    public const STATE_PATH = 'evaluation/dolved-care-engineering/v2/provisioning.json';

    public const ENGINEERING_SNAPSHOT = '/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json';

    public const CALIBRATION_SNAPSHOT = '/evaluation/calibration/corpus.json';

    public const CAL_EXP_0002_POPULATION_MANIFEST = '/evaluation/calibration/population-manifest.json';

    public const CAL_EXP_0002_COMPATIBILITY_RESULT = '/evaluation/calibration/composition-compatibility.json';

    public const ENGINEERING_CASE_IDS_DIGEST = 'fca770615b5fbf20e81b494454969d54dbab2bfa66abf728455e95832b57465f';

    public const ENGINEERING_SNAPSHOT_SOURCE_DIGEST = '0f67713c99ec6afc023e0f8e71bc746949d71ec8f2dd4dc858ed96417209b710';

    public const EXPECTED_FAMILIES = 71;

    public const EXPECTED_VERSIONS = 93;

    public const EXPECTED_ENGINEERING_CASES = 42;

    public const EXPECTED_ENGINEERING_VARIANTS = 126;

    private function __construct() {}
}
