<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentApplicabilityScope;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WorkspaceKnowledgeReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_includes_each_current_searchable_family_once_regardless_of_applicability_shape(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $universal = $this->searchableDocument($workspace, $owner, 'Universal policy');
        $oneLocation = $this->searchableDocument($workspace, $owner, 'Site policy');
        $severalLocations = $this->searchableDocument($workspace, $owner, 'Multi-site policy');
        $parentRegion = $this->searchableDocument($workspace, $owner, 'Regional policy');

        $region = OrganisationalLocation::factory()->for($workspace)->create(['kind' => 'region']);
        $siteA = OrganisationalLocation::factory()->for($workspace)->create(['parent_id' => $region->id, 'kind' => 'site']);
        $siteB = OrganisationalLocation::factory()->for($workspace)->create(['parent_id' => $region->id, 'kind' => 'site']);
        $this->specificApplicability($oneLocation, [$siteA->id]);
        $this->specificApplicability($severalLocations, [$siteA->id, $siteB->id]);
        $this->specificApplicability($parentRegion, [$region->id]);

        $this->actingAs($owner)->getJson($this->readinessUrl($workspace))
            ->assertOk()
            ->assertExactJson(['data' => ['searchable_document_count' => 4]]);

        $this->assertSame(DocumentApplicabilityScope::Universal, $universal->applicabilitySnapshot->scope);
    }

    public function test_count_excludes_every_non_searchable_authority_and_technical_state(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $this->searchableDocument($workspace, $owner, 'Searchable policy');

        foreach ([
            [DocumentStatus::Indexed, DocumentGovernanceStatus::Draft, now()->subDay(), null],
            [DocumentStatus::Failed, DocumentGovernanceStatus::Approved, now()->subDay(), now()->subDay()],
            [DocumentStatus::Deleted, DocumentGovernanceStatus::Approved, now()->subDay(), now()->subDay()],
            [DocumentStatus::Indexed, DocumentGovernanceStatus::Withdrawn, now()->subDay(), now()->subDay()],
            [DocumentStatus::Indexed, DocumentGovernanceStatus::Approved, now()->addDay(), now()],
        ] as $index => [$status, $governance, $effectiveFrom, $approvedAt]) {
            $family = DocumentFamily::factory()->for($workspace)->create([
                'owner_user_id' => $owner->id,
                'name' => "Unavailable {$index}",
            ]);
            Document::factory()->for($workspace)->for($family, 'family')->create([
                'status' => $status,
                'governance_status' => $governance,
                'effective_from' => $effectiveFrom,
                'approved_at' => $approvedAt,
                'withdrawn_at' => $governance === DocumentGovernanceStatus::Withdrawn ? now() : null,
                'failure_category' => $status === DocumentStatus::Failed ? 'extraction_failed' : null,
                'failure_message' => $status === DocumentStatus::Failed ? 'Synthetic failure.' : null,
            ]);
        }

        $this->actingAs($owner)->getJson($this->readinessUrl($workspace))
            ->assertOk()
            ->assertJsonPath('data.searchable_document_count', 1);
    }

    public function test_starter_questions_are_deterministic_and_only_use_searchable_family_titles(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $this->searchableDocument($workspace, $owner, 'Safeguarding procedure');
        $this->searchableDocument($workspace, $owner, 'Medication policy');
        $draftFamily = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'name' => 'Unapproved private draft',
        ]);
        Document::factory()->for($workspace)->for($draftFamily, 'family')->indexed()->create();

        $this->actingAs($owner)->getJson($this->starterUrl($workspace))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.question', 'What are the key points in Medication policy?')
            ->assertJsonPath('data.1.question', 'What are the key points in Safeguarding procedure?')
            ->assertJsonMissing(['question' => 'What are the key points in Unapproved private draft?']);
    }

    public function test_workspace_membership_conceals_cross_workspace_readiness_and_starters(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        [$otherOwner, $otherWorkspace] = $this->ownerWorkspace();
        $this->searchableDocument($otherWorkspace, $otherOwner, 'Other workspace policy');

        $this->actingAs($owner)->getJson($this->readinessUrl($otherWorkspace))->assertNotFound();
        $this->actingAs($owner)->getJson($this->starterUrl($otherWorkspace))->assertNotFound();
        $this->actingAs($owner)->getJson($this->readinessUrl($workspace))->assertJsonPath('data.searchable_document_count', 0);
    }

    public function test_searchable_library_filter_uses_the_same_current_definition(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $searchable = $this->searchableDocument($workspace, $owner, 'Current policy');
        $draft = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        Document::factory()->for($workspace)->for($draft, 'family')->indexed()->create();

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->public_id}/document-library?searchable=true")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $searchable->family->public_id);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();

        return [$owner, $workspace];
    }

    private function searchableDocument(Workspace $workspace, User $owner, string $name): Document
    {
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
        ]);

        return Document::factory()->for($workspace)->for($family, 'family')->indexed()->approved()->create([
            'effective_from' => now()->subDay(),
            'approved_at' => now()->subDay(),
        ]);
    }

    /** @param list<int> $locationIds */
    private function specificApplicability(Document $document, array $locationIds): void
    {
        $snapshot = $document->applicabilitySnapshot;
        DB::table('document_applicability_snapshots')->where('id', $snapshot->id)->update([
            'scope' => DocumentApplicabilityScope::Specific->value,
        ]);
        $snapshot->locations()->attach($locationIds, ['workspace_id' => $document->workspace_id]);
    }

    private function readinessUrl(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/knowledge-readiness";
    }

    private function starterUrl(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/starter-questions";
    }
}
