<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentGovernanceStatus;
use App\Models\Document;
use App\Models\Workspace;
use App\Services\Generation\ResolvedEvidenceTemporalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolvedEvidenceTemporalStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_future_historical_and_withdrawn_status_uses_the_existing_timeline(): void
    {
        $workspace = Workspace::factory()->create();
        $at = CarbonImmutable::parse('2026-11-06T10:27:00+00:00');
        $prior = Document::factory()->indexed()->create([
            'workspace_id' => $workspace->id,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => CarbonImmutable::parse('2025-03-31T23:59:59+00:00'),
            'effective_from' => CarbonImmutable::parse('2025-04-01T00:00:00+00:00'),
        ]);
        $current = Document::factory()->indexed()->create([
            'workspace_id' => $workspace->id,
            'document_family_id' => $prior->document_family_id,
            'predecessor_document_id' => $prior->id,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => CarbonImmutable::parse('2026-08-31T23:59:59+00:00'),
            'effective_from' => CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
        ]);
        $future = Document::factory()->indexed()->create([
            'workspace_id' => $workspace->id,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => CarbonImmutable::parse('2026-10-31T23:59:59+00:00'),
            'effective_from' => CarbonImmutable::parse('2026-12-01T00:00:00+00:00'),
        ]);
        $withdrawn = Document::factory()->indexed()->create([
            'workspace_id' => $workspace->id,
            'governance_status' => DocumentGovernanceStatus::Withdrawn,
            'approved_at' => CarbonImmutable::parse('2026-04-30T23:59:59+00:00'),
            'effective_from' => CarbonImmutable::parse('2026-05-01T00:00:00+00:00'),
            'withdrawn_at' => CarbonImmutable::parse('2026-10-01T00:00:00+00:00'),
        ]);
        $status = app(ResolvedEvidenceTemporalStatus::class);

        $this->assertSame([
            'evaluated_at' => $at->toIso8601String(),
            'authority_start' => '2026-09-01T00:00:00+00:00',
            'authoritative_at_evaluated_at' => true,
        ], $status->forDocument($current, $at));
        $this->assertFalse($status->forDocument($prior, $at)['authoritative_at_evaluated_at']);
        $this->assertTrue($status->forDocument($prior, CarbonImmutable::parse('2025-06-01T00:00:00+00:00'))['authoritative_at_evaluated_at']);
        $this->assertFalse($status->forDocument($future, $at)['authoritative_at_evaluated_at']);
        $this->assertTrue($status->forDocument($future, CarbonImmutable::parse('2026-12-01T00:00:00+00:00'))['authoritative_at_evaluated_at']);
        $this->assertFalse($status->forDocument($withdrawn, $at)['authoritative_at_evaluated_at']);
        $this->assertTrue($status->forDocument($withdrawn, CarbonImmutable::parse('2026-09-01T00:00:00+00:00'))['authoritative_at_evaluated_at']);
    }
}
