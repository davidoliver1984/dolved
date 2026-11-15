<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Services\Retrieval\CoverageSelectionClient;
use Mockery\MockInterface;

/** Existing route fixtures exercise the declared optional-selection fallback. */
trait WithUnavailableCoverageSelector
{
    protected function fakeUnavailableCoverageSelector(): void
    {
        $this->mock(CoverageSelectionClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('select')->andReturn(['status' => 'unavailable', 'reason' => 'service_unavailable',
                'proposal' => null, 'usage' => ['stage' => 'coverage_selection', 'provider' => 'controlled', 'model' => 'fixture-only',
                    'execution' => 'local', 'request_count' => 0, 'retry_count' => 0,
                    'input_tokens' => 0, 'output_tokens' => 0, 'latency_ms' => 0, 'cost_basis' => 'unavailable', 'cost_usd' => null]]);
        });
    }
}
