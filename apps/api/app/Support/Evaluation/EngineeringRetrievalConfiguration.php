<?php

declare(strict_types=1);

namespace App\Support\Evaluation;

final class EngineeringRetrievalConfiguration
{
    public const VERSION = 'engineering-rrf-k-5-after-exp0004';

    public const RRF_K = 5;

    public const EVIDENCE_THRESHOLD = 0.337890625;

    public const DECISION = 'rrf_k=5 is the frozen engineering configuration carried forward into subsequent evaluation. It remains subject to calibration and sealed held-out acceptance.';

    private function __construct() {}
}
