<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Generation\UnresolvedNamedCoverageDisclosure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnresolvedNamedCoverageDisclosureTest extends TestCase
{
    public static function unresolvedOakfieldCases(): array
    {
        return [
            '141 two needs' => [2],
            '142 one need' => [1],
        ];
    }

    #[DataProvider('unresolvedOakfieldCases')]
    public function test_named_unresolved_retrieval_discloses_coverage_once(int $needCount): void
    {
        $needs = array_fill(0, $needCount, $this->need('retrieve', 'named', null));
        $text = UnresolvedNamedCoverageDisclosure::append('The key-safe review rule.', $needs);

        $this->assertSame(1, substr_count($text, 'Location coverage not established'));
        $this->assertStringContainsString('its applicability to that location has not been established', $text);
        $this->assertStringStartsWith('The key-safe review rule.', $text);
    }

    public function test_scope_fact_and_generic_second_need_do_not_change_case_15_display(): void
    {
        $needs = [
            $this->need('scope_enquiry', 'named', null),
            $this->need('retrieve', 'generic', null),
        ];

        $this->assertSame('Recorded Oakfield Lodge scope.', UnresolvedNamedCoverageDisclosure::append('Recorded Oakfield Lodge scope.', $needs));
    }

    public function test_resolved_named_location_and_non_evidence_outcome_are_unchanged(): void
    {
        $resolved = $this->need('retrieve', 'named', 'registered-location-id');
        $noEvidence = $this->need('retrieve', 'named', null);
        $noEvidence['retrieval_outcome'] = 'insufficient_evidence';

        $this->assertSame('Guidance.', UnresolvedNamedCoverageDisclosure::append('Guidance.', [$resolved]));
        $this->assertSame('Guidance.', UnresolvedNamedCoverageDisclosure::append('Guidance.', [$noEvidence]));
    }

    private function need(string $operation, string $applicability, ?string $location): array
    {
        return [
            'operation' => $operation,
            'applicability' => ['kind' => $applicability, 'source' => $applicability === 'named' ? ['quote' => 'Oakfield outreach', 'turn_id' => 'user-1'] : null],
            'retrieval_outcome' => 'evidence_found',
            'resolved_applicability_location' => ['resolved_location_public_id' => $location],
        ];
    }

    public function test_only_positive_non_site_classifications_suppress_unresolved_notice(): void
    {
        foreach (['organisation', 'document'] as $kind) {
            $need = $this->need('retrieve', 'named', null);
            $need['applicability_reference_classifications'] = [['reference' => 'grounded reference', 'kind' => $kind]];
            $this->assertSame('Guidance.', UnresolvedNamedCoverageDisclosure::append('Guidance.', [$need]));
            $need['applicability_reference_classifications'][] = ['reference' => 'Willow outreach', 'kind' => 'unresolved'];
            $this->assertStringContainsString('Location coverage not established', UnresolvedNamedCoverageDisclosure::append('Guidance.', [$need]));
        }
        $need['applicability_reference_classifications'] = [['kind' => 'model_said_generic']];
        $this->assertStringContainsString('Location coverage not established', UnresolvedNamedCoverageDisclosure::append('Guidance.', [$need]));
    }
}
