<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\GenerationServiceAuthenticationException;
use App\Support\Generation\GenerationValidationDiagnostics;
use PHPUnit\Framework\TestCase;

final class GenerationDiagnosticBoundaryTest extends TestCase
{
    public function test_only_bounded_ownership_identifiers_survive(): void
    {
        $context = ['need_id' => 'n1', 'answer_part_index' => 0, 'cited_ids' => ['ev-02'], 'allowed_ids' => ['ev-01'], 'text' => 'PRIVATE'];
        $diagnostic = ['field' => 'answer_parts.evidence_ids', 'code' => 'citation_ownership', 'ownership' => $context];
        $safe = GenerationValidationDiagnostics::remote($diagnostic);
        $this->assertArrayHasKey('ownership', $safe);
        $this->assertStringNotContainsString('PRIVATE', json_encode($safe));
        foreach (['need_id' => 'PRIVATE', 'answer_part_index' => 999, 'cited_ids' => ['PRIVATE'], 'allowed_ids' => array_fill(0, 6, 'ev-01')] as $key => $value) {
            $bad = $diagnostic;
            $bad['ownership'][$key] = $value;
            $this->assertArrayNotHasKey('ownership', GenerationValidationDiagnostics::remote($bad));
        }
    }

    public function test_unspecified_authentication_rejection_is_not_assumed_to_be_replay(): void
    {
        $safe = GenerationValidationDiagnostics::local(new GenerationServiceAuthenticationException(false));
        $this->assertSame('authentication_rejected', $safe['code']);
        $this->assertSame(401, $safe['http_status']);
        $this->assertSame('service_authentication_failure', $safe['category']);
    }
}
