<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Evaluation\BuildCurrentRetrievalEligibilityArtifact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CurrentRetrievalEligibilityArtifactTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_resolver_exports_typed_temporal_comparison_location_and_controlled_outcomes(): void
    {
        $artifact = $this->build();
        $entries = collect($artifact['entries'])->keyBy('variant_id');

        $this->assertSame('evidence_found', $entries['current']['outcome']);
        $this->assertCount(2, $entries['current']['document_public_ids_by_side']['primary']);
        $this->assertSame('evidence_found', $entries['compare']['outcome']);
        $this->assertNotEmpty($entries['compare']['document_public_ids_by_side']['primary']);
        $this->assertNotEmpty($entries['compare']['document_public_ids_by_side']['comparison']);
        $this->assertEmpty(array_intersect(
            $entries['compare']['document_public_ids_by_side']['primary'],
            $entries['compare']['document_public_ids_by_side']['comparison'],
        ));
        $versionByPublic = collect($artifact['documents'])->pluck('evaluation_document_version_id', 'public_document_id');
        $atDate = collect($entries['valid-at-date']['document_public_ids_by_side']['primary'])
            ->map(fn (string $id): string => $versionByPublic[$id])->all();
        $this->assertContains('doc.policy.v1', $atDate);
        $this->assertNotContains('doc.policy.v2', $atDate);
        $this->assertSame('evidence_found', $entries['location']['outcome']);
        $this->assertNotNull($entries['location']['resolved_location_public_id']);
        $this->assertSame('clarification_required', $entries['unknown']['outcome']);
        $this->assertSame('unresolved_location_reference', $entries['unknown']['reason']);
        $this->assertSame('evidence_found', $entries['coventry']['outcome']);
        $this->assertSame('evidence_found', $entries['midlands']['outcome']);
        $this->assertSame('evidence_found', $entries['bristol-hierarchy']['outcome']);
        $this->assertSame('evidence_found', $entries['meadow-hierarchy']['outcome']);
        $this->assertSame(
            $entries['coventry']['resolved_location_public_id'],
            $entries['coventry-hierarchy']['resolved_location_public_id'],
        );
        $this->assertSame('no_eligible_evidence', $artifact['probes']['no_active_corpus_generation']['outcome']);
        $this->assertSame(0, $artifact['isolation']['cross_workspace_document_count_in_scopes']);
        $this->assertSame('App\\Services\\Retrieval\\EligibilityResolver', $artifact['resolver']['implementation']);
    }

    public function test_authority_state_changes_real_resolver_scope_without_expected_evidence_input(): void
    {
        $artifact = $this->build(successorEffectiveFrom: '2027-01-01T00:00:00Z');
        $current = collect($artifact['entries'])->firstWhere('variant_id', 'current');
        $versionByPublic = collect($artifact['documents'])->pluck('evaluation_document_version_id', 'public_document_id');
        $versions = collect($current['document_public_ids_by_side']['primary'])
            ->map(fn (string $id): string => $versionByPublic[$id])->all();

        $this->assertContains('doc.policy.v1', $versions);
        $this->assertNotContains('doc.policy.v2', $versions);
        $this->assertStringNotContainsString('evidence_unit', json_encode($this->plans(), JSON_THROW_ON_ERROR));
    }

    public function test_applicability_state_changes_real_resolver_scope(): void
    {
        $artifact = $this->build(locationApplicability: ['location.other']);
        $location = collect($artifact['entries'])->firstWhere('variant_id', 'location');
        $versionByPublic = collect($artifact['documents'])->pluck('evaluation_document_version_id', 'public_document_id');
        $versions = collect($location['document_public_ids_by_side']['primary'])
            ->map(fn (string $id): string => $versionByPublic[$id])->all();

        $this->assertNotContains('doc.location.v1', $versions);
        $this->assertContains('doc.policy.v2', $versions);
    }

    public function test_console_boundary_refuses_non_e2e_identity(): void
    {
        $this->artisan('evaluation:resolve-current-eligibility', ['--run' => 'test-run'])
            ->expectsOutputToContain('restricted to the isolated dolved-e2e identity')
            ->assertFailed();
    }

    public function test_no_public_route_exposes_the_evaluation_boundary(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $this->assertFalse($routes->contains(
            fn ($route): bool => str_contains($route->uri(), 'resolve-current-eligibility')
                || str_contains((string) $route->getActionName(), 'BuildCurrentRetrievalEligibilityArtifact'),
        ));
    }

    /** @param list<string> $locationApplicability @return array<string, mixed> */
    private function build(
        string $successorEffectiveFrom = '2025-01-01T00:00:00Z',
        array $locationApplicability = ['location.region'],
    ): array {
        $directory = storage_path('framework/testing/current-retrieval-'.bin2hex(random_bytes(4)));
        mkdir($directory, 0755, true);
        $catalog = $this->catalog($successorEffectiveFrom, $locationApplicability);
        $organisation = $this->organisation();
        $plans = $this->plans();
        file_put_contents($directory.'/catalog.json', json_encode($catalog, JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/organisation.json', json_encode($organisation, JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/plans.json', json_encode($plans, JSON_THROW_ON_ERROR));

        return app(BuildCurrentRetrievalEligibilityArtifact::class)->handle(
            'focused-resolver-test',
            str_repeat('a', 40),
            $directory.'/catalog.json',
            $directory.'/organisation.json',
            $directory.'/plans.json',
            '/contracts/evaluation/v2/deterministic-eligibility-artifact.schema.json',
            10,
        );
    }

    /** @param list<string> $locationApplicability @return array<string, mixed> */
    private function catalog(string $successorEffectiveFrom, array $locationApplicability): array
    {
        $version = fn (string $id, string $effective, ?string $predecessor, array $locations = []): array => [
            'version_id' => $id, 'version_number' => '1', 'source_path' => "documents/{$id}.md",
            'governance_state' => 'APPROVED', 'created_at' => '2022-01-01T00:00:00Z',
            'approved_at' => '2022-01-02T00:00:00Z', 'effective_from' => $effective,
            'withdrawn_at' => null, 'supersedes_version_id' => $predecessor,
            'applicability' => ['kind' => $locations === [] ? 'UNIVERSAL' : 'SPECIFIC', 'location_ids' => $locations],
            'pilot' => false,
        ];

        return [
            'schema_version' => 'v2', 'benchmark_id' => 'dolved-care-engineering', 'catalog_version' => '1',
            'families' => [
                ['family_id' => 'family.policy', 'domain' => 'test', 'title' => 'Policy', 'document_type' => 'POLICY', 'planned_phenomena' => [], 'relationships' => [], 'versions' => [
                    $version('doc.policy.v1', '2023-01-01T00:00:00Z', null),
                    $version('doc.policy.v2', $successorEffectiveFrom, 'doc.policy.v1'),
                ]],
                ['family_id' => 'family.location', 'domain' => 'test', 'title' => 'Location policy', 'document_type' => 'POLICY', 'planned_phenomena' => [], 'relationships' => [], 'versions' => [
                    $version('doc.location.v1', '2023-01-01T00:00:00Z', null, $locationApplicability),
                ]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function organisation(): array
    {
        return [
            'schema_version' => 'v2', 'benchmark_id' => 'dolved-care-engineering',
            'evaluation_clock' => '2026-08-01T12:00:00Z', 'organisation' => ['name' => 'Test'], 'terminology' => [],
            'locations' => [
                ['location_id' => 'location.region', 'name' => 'Region', 'kind' => 'REGION', 'parent_location_id' => null],
                ['location_id' => 'location.site', 'name' => 'Site', 'kind' => 'SITE', 'parent_location_id' => 'location.region'],
                ['location_id' => 'location.other', 'name' => 'Other', 'kind' => 'REGION', 'parent_location_id' => null],
                ['location_id' => 'location.region.midlands', 'name' => 'Midlands Region', 'kind' => 'REGION', 'parent_location_id' => null],
                ['location_id' => 'location.willow-bank', 'name' => 'Willow Bank Community Service', 'kind' => 'SERVICE', 'parent_location_id' => 'location.region.midlands'],
                ['location_id' => 'location.region.south-west', 'name' => 'South West Region', 'kind' => 'REGION', 'parent_location_id' => null],
                ['location_id' => 'location.bristol', 'name' => 'Bristol', 'kind' => 'CITY', 'parent_location_id' => 'location.region.south-west'],
                ['location_id' => 'location.meadow-court', 'name' => 'Meadow Court', 'kind' => 'SITE', 'parent_location_id' => 'location.region.south-west'],
            ],
            'aliases' => [
                ['alias' => 'site alias', 'location_ids' => ['location.site']],
                ['alias' => 'Coventry', 'location_ids' => ['location.willow-bank']],
                ['alias' => 'Midlands', 'location_ids' => ['location.region.midlands']],
                ['alias' => 'South West', 'location_ids' => ['location.region.south-west']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function plans(): array
    {
        $contract = fn (string $mode, ?array $reference = null, array $locations = [], ?string $date = null): array => [
            'contract_version' => 2, 'temporal_mode' => $mode, 'explicit_date' => $date,
            'temporal_reference' => $reference, 'location_references' => $locations, 'clarification_reason' => null,
        ];

        return [
            'schema_version' => 'v2', 'scope' => 'engineering_tuning', 'expectations' => [
                ['case_id' => 'case.current', 'variant_id' => 'current', 'question' => 'Current?', 'contract' => $contract('current')],
                ['case_id' => 'case.compare', 'variant_id' => 'compare', 'question' => 'Compare?', 'contract' => $contract('compare')],
                ['case_id' => 'case.at-date', 'variant_id' => 'valid-at-date', 'question' => 'At 15 June 2024?', 'contract' => $contract('valid_at_date', date: '2024-06-15')],
                ['case_id' => 'case.location', 'variant_id' => 'location', 'question' => 'At site?', 'contract' => $contract('current', locations: ['site alias'])],
                ['case_id' => 'case.unknown', 'variant_id' => 'unknown', 'question' => 'There?', 'contract' => $contract('current', locations: ['there'])],
                ['case_id' => 'case.coventry', 'variant_id' => 'coventry', 'question' => 'At Coventry?', 'contract' => $contract('current', locations: ['Coventry'])],
                ['case_id' => 'case.midlands', 'variant_id' => 'midlands', 'question' => 'In the Midlands?', 'contract' => $contract('current', locations: ['Midlands'])],
                ['case_id' => 'case.coventry-hierarchy', 'variant_id' => 'coventry-hierarchy', 'question' => 'At Coventry in the Midlands?', 'contract' => $contract('current', locations: ['Coventry', 'Midlands'])],
                ['case_id' => 'case.bristol', 'variant_id' => 'bristol-hierarchy', 'question' => 'At Bristol in the South West?', 'contract' => $contract('current', locations: ['South West', 'Bristol'])],
                ['case_id' => 'case.meadow', 'variant_id' => 'meadow-hierarchy', 'question' => 'At Meadow Court in the South West?', 'contract' => $contract('current', locations: ['South West', 'Meadow Court'])],
            ],
        ];
    }
}
