<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class PlannerConfigurationTest extends TestCase
{
    public function test_current_planner_lineage_matches_adr_0022_v5(): void
    {
        $lineage = [
            'provider' => (string) config('retrieval.planner.provider'),
            'model' => (string) config('retrieval.planner.model'),
            'contract_schema_version' => (string) config('retrieval.planner.contract_schema_version'),
            'prompt_version' => (string) config('retrieval.planner.prompt_version'),
            'adapter_version' => (string) config('retrieval.planner.adapter_version'),
        ];
        $fingerprintInput = $lineage;
        ksort($fingerprintInput);
        $fingerprint = hash(
            'sha256',
            json_encode($fingerprintInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        $this->assertSame('plan-response-v2', $lineage['contract_schema_version']);
        $this->assertSame('adr-0022-v5', $lineage['prompt_version']);
        $this->assertSame('structured-chat-v3', $lineage['adapter_version']);
        $this->assertSame(
            'b18ce9cfcb769bbe2c2d28e74ba9b1ffa90a62c887de7e9b04d595cc6a1cf690',
            $fingerprint,
        );
    }
}
