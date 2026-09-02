<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Actions\Documents\UpdateDocumentFamilyMetadata;
use App\Actions\Ingestion\RecordIngestionAudit;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentFamilyActivitySummary;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Documents\MaintainDocumentFamilyActivitySummary;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DocumentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_returns_one_family_row_with_authoritative_current_version(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'name' => 'Medication procedure',
        ]);
        $document = Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'effective_from' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'source_checksum_sha256' => str_repeat('a', 64),
            'checksum_verification_status' => ChecksumVerificationStatus::Verified,
            'source_filename' => 'medication-v1.pdf',
        ]);

        $this->actingAs($owner)->getJson($this->url($workspace))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $family->public_id)
            ->assertJsonPath('data.0.state', 'current')
            ->assertJsonPath('data.0.current_version.public_id', $document->public_id)
            ->assertJsonPath('data.0.current_version.source_filename', 'medication-v1.pdf')
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_library_states_are_truthful_and_historical_is_opt_in(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $scheduled = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        Document::factory()->for($workspace)->for($scheduled, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'effective_from' => now()->addMonth(),
            'approved_at' => now(),
        ]);
        $draft = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        Document::factory()->for($workspace)->for($draft, 'family')->processing()->create();
        $historical = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        Document::factory()->for($workspace)->for($historical, 'family')->withdrawn()->create(['status' => DocumentStatus::Indexed]);

        $response = $this->actingAs($owner)->getJson($this->url($workspace))->assertOk();
        $states = collect($response->json('data'))->pluck('state', 'public_id');
        $this->assertSame('scheduled', $states[$scheduled->public_id]);
        $this->assertSame('processing', $states[$draft->public_id]);
        $this->assertArrayNotHasKey($historical->public_id, $states->all());

        $this->actingAs($owner)->getJson($this->url($workspace).'?historical=true')
            ->assertOk()->assertJsonFragment(['public_id' => $historical->public_id, 'historical' => true]);
    }

    public function test_missing_summary_uses_family_creation_and_exact_rebuild_repairs_future_value(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        Document::factory()->for($workspace)->for($family, 'family')->processing()->create();
        $this->assertDatabaseMissing('document_family_activity_summary', ['family_id' => $family->id]);
        $this->actingAs($owner)->getJson($this->url($workspace))->assertOk()->assertJsonCount(1, 'data');

        DocumentFamilyActivitySummary::query()->create([
            'family_id' => $family->id,
            'last_meaningful_update' => now()->addYear(),
        ]);
        $expected = app(MaintainDocumentFamilyActivitySummary::class)->rebuild($family);
        $this->assertTrue($expected->lessThan(now()->addDay()));
        $this->assertSame($expected->toIso8601String(), $family->activitySummary()->firstOrFail()->last_meaningful_update->toIso8601String());
    }

    public function test_withdrawn_successor_does_not_resurrect_or_expose_its_predecessor_by_default(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $predecessor = Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'effective_from' => now()->subYears(2),
            'approved_at' => now()->subYears(2),
            'withdrawn_at' => null,
        ]);
        Document::factory()->for($workspace)->for($family, 'family')->create([
            'predecessor_document_id' => $predecessor->id,
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Withdrawn,
            'effective_from' => now()->subYear(),
            'approved_at' => now()->subYear(),
            'withdrawn_at' => now()->subMonth(),
        ]);

        $this->actingAs($owner)->getJson($this->url($workspace))
            ->assertOk()
            ->assertJsonMissing(['public_id' => $family->public_id]);

        $this->actingAs($owner)->getJson($this->url($workspace).'?historical=true')
            ->assertOk()
            ->assertJsonFragment(['public_id' => $family->public_id, 'historical' => true]);
    }

    public function test_invalid_query_values_degrade_to_the_documented_defaults(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        Document::factory()->for($workspace)->for($family, 'family')->processing()->create();

        $this->actingAs($owner)->getJson($this->url($workspace).'?status=unknown&sort=unknown&direction=sideways&per_page=999&page=-4&category=not-a-uuid')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $family->public_id)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_live_producers_advance_activity_monotonically_in_their_authoritative_transactions(): void
    {
        $this->travelTo('2026-08-01 09:00:00');
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $document = Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Draft,
            'effective_from' => now()->subDay(),
        ]);

        $this->travelTo('2026-08-02 09:00:00');
        app(ApproveDocumentVersion::class)->handle($document, $owner);
        $this->assertSame(now()->toIso8601String(), $family->activitySummary()->firstOrFail()->last_meaningful_update->toIso8601String());

        $this->travelTo('2026-08-03 09:00:00');
        app(UpdateDocumentFamilyMetadata::class)->handle($family, $owner, 'Reviewed family metadata.', null, null);
        $this->assertSame(now()->toIso8601String(), $family->activitySummary()->firstOrFail()->last_meaningful_update->toIso8601String());

        $this->travelTo('2026-08-04 09:00:00');
        $attempt = IngestionEventClaim::factory()->for($document)->create();
        app(RecordIngestionAudit::class)->handle($attempt, 'publication_completed', 'indexed');
        $this->assertSame(now()->toIso8601String(), $family->activitySummary()->firstOrFail()->last_meaningful_update->toIso8601String());

        app(MaintainDocumentFamilyActivitySummary::class)->record($family, now()->subDays(10));
        $this->assertSame(now()->toIso8601String(), $family->activitySummary()->firstOrFail()->last_meaningful_update->toIso8601String());
    }

    public function test_query_count_is_bounded_for_a_full_page(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        foreach (range(1, 100) as $index) {
            $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id, 'name' => "Policy {$index}"]);
            Document::factory()->for($workspace)->for($family, 'family')->processing()->create();
        }
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->actingAs($owner)->getJson($this->url($workspace).'?per_page=100')->assertOk()->assertJsonCount(100, 'data');
        $this->assertLessThanOrEqual(9, count($queries), implode("\n", $queries));
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();

        return [$owner, $workspace];
    }

    private function url(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-library";
    }
}
