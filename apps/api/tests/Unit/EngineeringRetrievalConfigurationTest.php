<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\EngineeringRetrievalConfiguration;
use PHPUnit\Framework\TestCase;

final class EngineeringRetrievalConfigurationTest extends TestCase
{
    public function test_exp_0004_freezes_only_the_engineering_rrf_value(): void
    {
        $this->assertSame('engineering-rrf-k-5-after-exp0004', EngineeringRetrievalConfiguration::VERSION);
        $this->assertSame(5, EngineeringRetrievalConfiguration::RRF_K);
        $this->assertSame(0.337890625, EngineeringRetrievalConfiguration::EVIDENCE_THRESHOLD);
        $this->assertSame(
            'rrf_k=5 is the frozen engineering configuration carried forward into subsequent evaluation. It remains subject to calibration and sealed held-out acceptance.',
            EngineeringRetrievalConfiguration::DECISION,
        );
    }
}
