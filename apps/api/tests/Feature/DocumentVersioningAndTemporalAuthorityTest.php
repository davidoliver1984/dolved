<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Actions\Documents\CorrectDocumentGovernanceTimestamps;
use App\Actions\Documents\CreateDocument;
use App\Actions\Documents\CreateDocumentVersion;
use App\Actions\Documents\RescheduleDocumentVersion;
use App\Actions\Documents\WithdrawDocumentVersion;
use App\Enums\DocumentApplicabilityScope;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\WorkspaceRole;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Queries\Documents\ResolveAuthoritativeDocument;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class DocumentVersioningAndTemporalAuthorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_creation_atomically_creates_family_and_sealed_universal_snapshot(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();

        $document = app(CreateDocument::class)->handle($workspace, $owner, 'Policy.pdf', 'application/pdf', 100);

        $this->assertTrue($document->family->workspace->is($workspace));
        $this->assertSame(DocumentGovernanceStatus::Draft, $document->governance_status);
        $this->assertNull($document->predecessor);
        $this->assertSame(DocumentApplicabilityScope::Universal, $document->applicabilitySnapshot->scope);
        $this->assertNotNull($document->applicabilitySnapshot->sealed_at);
        $this->assertTrue($workspace->fresh()->documentFamilies->contains($document->family));
    }

    public function test_successor_preserves_family_lineage_and_copies_applicability(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $location = OrganisationalLocation::factory()->for($workspace)->create();
        $first = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        DB::table('document_applicability_snapshots')->where('document_id', $first->id)->update(['sealed_at' => null, 'scope' => 'specific']);
        $first->applicabilitySnapshot->locations()->attach($location->id, ['workspace_id' => $workspace->id]);
        $first->applicabilitySnapshot->update(['sealed_at' => now()]);

        $second = app(CreateDocumentVersion::class)->handle(
            $first, $owner, 'Policy v2.pdf', 'application/pdf', 120, CarbonImmutable::parse('2026-02-01'),
        );

        $this->assertTrue($second->family->is($first->family));
        $this->assertTrue($second->predecessor->is($first));
        $this->assertTrue($first->fresh()->successor->is($second));
        $this->assertTrue($second->applicabilitySnapshot->locations->contains($location));
        $this->assertSame(DocumentApplicabilityScope::Specific, $second->applicabilitySnapshot->scope);

        $this->expectException(DocumentGovernanceException::class);
        app(CreateDocumentVersion::class)->handle(
            $first, $owner, 'Branch.pdf', 'application/pdf', 50, CarbonImmutable::parse('2026-03-01'),
        );
    }

    public function test_current_and_valid_at_date_are_derived_from_effective_and_approval_facts(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $first = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $second = $this->successor($first, $owner, CarbonImmutable::parse('2026-02-01'));

        $this->travelTo('2026-01-10 12:00:00');
        app(ApproveDocumentVersion::class)->handle($first, $owner);
        $this->travelTo('2026-03-01 12:00:00');
        app(ApproveDocumentVersion::class)->handle($second, $owner);

        $resolver = app(ResolveAuthoritativeDocument::class);
        $this->assertTrue($resolver->validAtDate($first->family, CarbonImmutable::parse('2026-02-15'))->is($first));
        $this->assertTrue($resolver->validAtDate($first->family, CarbonImmutable::parse('2026-03-01 12:00:00'))->is($second));
        $this->assertTrue($resolver->current($first->family)->is($second));
    }

    public function test_withdrawal_ends_authority_without_resurrecting_a_predecessor(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $first = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $second = $this->successor($first, $owner, CarbonImmutable::parse('2026-02-01'));
        $this->travelTo('2026-01-02');
        app(ApproveDocumentVersion::class)->handle($first, $owner);
        $this->travelTo('2026-02-02');
        app(ApproveDocumentVersion::class)->handle($second, $owner);
        $this->travelTo('2026-03-01');
        app(WithdrawDocumentVersion::class)->handle($second, $owner);

        $this->assertNull(app(ResolveAuthoritativeDocument::class)->validAtDate($first->family, now()->addDay()));
    }

    public function test_cancelled_future_successor_is_skipped_without_creating_a_gap(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $first = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $future = $this->successor($first, $owner, CarbonImmutable::parse('2026-06-01'));
        $this->travelTo('2026-02-01');
        app(ApproveDocumentVersion::class)->handle($first, $owner);
        $this->travelTo('2026-03-01');
        app(ApproveDocumentVersion::class)->handle($future, $owner);
        $this->travelTo('2026-04-01');
        app(WithdrawDocumentVersion::class)->handle($future, $owner);

        $resolved = app(ResolveAuthoritativeDocument::class)->validAtDate($first->family, CarbonImmutable::parse('2026-07-01'));
        $this->assertTrue($resolved?->is($first));
    }

    public function test_approval_rejects_non_monotonic_authority_in_lineage(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $first = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $second = $this->successor($first, $owner, CarbonImmutable::parse('2026-02-01'));
        $this->travelTo('2026-03-01');
        app(ApproveDocumentVersion::class)->handle($second, $owner);
        $this->travelTo('2026-04-01');

        $this->expectException(DocumentGovernanceException::class);
        app(ApproveDocumentVersion::class)->handle($first, $owner);
    }

    public function test_approved_future_version_can_be_rescheduled_only_before_attaining_authority(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $first = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $future = $this->successor($first, $owner, CarbonImmutable::parse('2026-06-01'));
        $this->travelTo('2026-02-01');
        app(ApproveDocumentVersion::class)->handle($first, $owner);
        $this->travelTo('2026-03-01');
        $future = app(ApproveDocumentVersion::class)->handle($future, $owner);

        $rescheduled = app(RescheduleDocumentVersion::class)->handle(
            $future, $owner, CarbonImmutable::parse('2026-07-01'),
        );
        $this->assertSame('2026-07-01', $rescheduled->effective_from->toDateString());

        $this->travelTo('2026-07-02');
        $this->expectException(DocumentGovernanceException::class);
        app(RescheduleDocumentVersion::class)->handle(
            $rescheduled, $owner, CarbonImmutable::parse('2026-08-01'),
        );
    }

    public function test_historical_correction_is_owner_only_requires_reason_and_is_audited(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $admin = User::factory()->create();
        WorkspaceMembership::factory()->admin()->for($workspace)->for($admin)->create();
        $document = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $this->travelTo('2026-02-01');
        $document = app(ApproveDocumentVersion::class)->handle($document, $owner);

        try {
            app(CorrectDocumentGovernanceTimestamps::class)->handle(
                $document, $admin, CarbonImmutable::parse('2026-01-15'), null, 'Imported evidence',
            );
            $this->fail('An administrator must not perform a historical correction.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $corrected = app(CorrectDocumentGovernanceTimestamps::class)->handle(
            $document, $owner, CarbonImmutable::parse('2026-01-15'), null, 'Imported approval evidence',
        );
        $event = $corrected->governanceAuditEvents()->latest('id')->firstOrFail();
        $this->assertSame('historical_timestamps_corrected', $event->action);
        $this->assertSame('Imported approval evidence', $event->reason);
        $this->assertTrue($event->actor->is($owner));
    }

    public function test_applicability_supports_arbitrary_depth_and_sealed_snapshots_are_immutable(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        $root = OrganisationalLocation::factory()->for($workspace)->create();
        $region = OrganisationalLocation::factory()->for($workspace)->create(['parent_id' => $root->id]);
        $site = OrganisationalLocation::factory()->for($workspace)->create(['parent_id' => $region->id]);
        $document = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));

        $this->assertTrue($site->parent->is($region));
        $this->assertTrue($region->parent->is($root));

        try {
            $document->applicabilitySnapshot->update(['scope' => DocumentApplicabilityScope::Specific]);
            $this->fail('A sealed applicability snapshot must not be mutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $root->update(['parent_id' => $site->id]);
    }

    public function test_cross_workspace_lineage_and_applicability_fail_closed(): void
    {
        [$workspace, $owner] = $this->workspaceOwner();
        [$otherWorkspace] = $this->workspaceOwner();
        $document = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $otherLocation = OrganisationalLocation::factory()->for($otherWorkspace)->create();

        $this->expectException(DocumentGovernanceException::class);
        app(CreateDocumentVersion::class)->handle(
            $document, $owner, 'v2.pdf', 'application/pdf', 10, CarbonImmutable::parse('2026-02-01'), [$otherLocation],
        );
    }

    public function test_schema_has_foundation_constraints_and_no_stored_current_flag(): void
    {
        foreach ([
            'document_families',
            'organisational_locations',
            'organisational_location_aliases',
            'document_family_default_applicabilities',
            'document_applicability_snapshots',
            'document_applicability_locations',
            'document_governance_audit_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertTrue(Schema::hasColumns('documents', [
            'document_family_id', 'predecessor_document_id', 'governance_status',
            'effective_from', 'approved_at', 'withdrawn_at',
        ]));
        $this->assertFalse(Schema::hasColumn('documents', 'is_current'));

        [$workspace, $owner] = $this->workspaceOwner();
        $document = $this->document($workspace, $owner, CarbonImmutable::parse('2026-01-01'));
        $this->expectException(QueryException::class);
        Document::factory()->create([
            'workspace_id' => $workspace->id,
            'document_family_id' => $document->document_family_id,
            'predecessor_document_id' => $document->id,
            'effective_from' => CarbonImmutable::parse('2026-02-01'),
        ]);
        Document::factory()->create([
            'workspace_id' => $workspace->id,
            'document_family_id' => $document->document_family_id,
            'predecessor_document_id' => $document->id,
            'effective_from' => CarbonImmutable::parse('2026-03-01'),
        ]);
    }

    /** @return array{Workspace, User} */
    private function workspaceOwner(): array
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($owner)->create(['role' => WorkspaceRole::Owner]);

        return [$workspace, $owner];
    }

    private function document(Workspace $workspace, User $creator, CarbonImmutable $effectiveFrom): Document
    {
        return Document::factory()->for($workspace)->for($creator, 'createdBy')->create([
            'effective_from' => $effectiveFrom,
        ]);
    }

    private function successor(Document $predecessor, User $creator, CarbonImmutable $effectiveFrom): Document
    {
        return app(CreateDocumentVersion::class)->handle(
            $predecessor, $creator, 'Successor.pdf', 'application/pdf', 100, $effectiveFrom,
        );
    }
}
