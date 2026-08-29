<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\ReconcileDocumentFamilyDeletion;
use App\Contracts\Documents\ExportSourceHold;
use App\Enums\DocumentContentCloneStatus;
use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentFamilyDeletionStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentContentCloneOperation;
use App\Models\DocumentFamily;
use App\Models\DocumentFamilyDeletionOperation;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentFamilyDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_read_only_state_bound_and_tenant_concealed(): void
    {
        [$owner, $workspace, $family, $versions] = $this->familyWithVersions();
        $before = [
            'families' => DocumentFamily::query()->count(),
            'documents' => Document::query()->count(),
            'operations' => DocumentFamilyDeletionOperation::query()->count(),
        ];

        $preview = $this->actingAs($owner)->postJson($this->previewUrl($workspace, $family))
            ->assertOk()
            ->assertJsonPath('data.counts.versions', 3)
            ->assertJsonPath('data.versions.0.classification', 'current')
            ->assertJsonPath('data.versions.1.classification', 'scheduled')
            ->assertJsonPath('data.versions.2.classification', 'draft')
            ->assertJsonPath('data.warning', 'Restoration is unavailable after completion. Existing citation snapshots survive, but source viewing disappears.');

        $this->assertSame($before, [
            'families' => DocumentFamily::query()->count(),
            'documents' => Document::query()->count(),
            'operations' => DocumentFamilyDeletionOperation::query()->count(),
        ]);
        foreach ($versions as $version) {
            $this->assertNotSame(DocumentStatus::Deleting, $version->fresh()->status);
        }

        $foreignWorkspace = Workspace::factory()->withOwner()->create();
        $this->actingAs($owner)
            ->postJson($this->previewUrl($foreignWorkspace, $family))
            ->assertNotFound();
        $this->assertIsString($preview->json('data.confirmation_digest'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $preview->json('data.confirmation_state_digest'));
    }

    public function test_confirm_rejects_stale_state_without_mutation(): void
    {
        [$owner, $workspace, $family, $versions] = $this->familyWithVersions();
        $digest = $this->actingAs($owner)->postJson($this->previewUrl($workspace, $family))->json('data.confirmation_digest');
        $versions[2]->forceFill(['status' => DocumentStatus::Failed, 'failure_category' => 'changed', 'failure_message' => 'changed'])->save();

        $this->actingAs($owner)->postJson($this->confirmUrl($workspace, $family), [
            'confirmation_digest' => $digest,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict()->assertJsonPath('error.code', 'family_deletion_conflict');

        $this->assertDatabaseCount('document_family_deletion_operations', 0);
        $this->assertDatabaseCount('document_deletion_operations', 0);
        $this->assertSame(DocumentGovernanceStatus::Approved, $versions[0]->fresh()->governance_status);
    }

    public function test_confirm_freezes_children_preserves_draft_truth_and_is_idempotent(): void
    {
        Queue::fake();
        [$owner, $workspace, $family, $versions] = $this->familyWithVersions();
        $preview = $this->actingAs($owner)->postJson($this->previewUrl($workspace, $family));
        $payload = [
            'confirmation_digest' => $preview->json('data.confirmation_digest'),
            'idempotency_key' => (string) Str::uuid(),
        ];

        $response = $this->actingAs($owner)->postJson($this->confirmUrl($workspace, $family), $payload)
            ->assertAccepted()
            ->assertJsonPath('data.operation.status', DocumentFamilyDeletionStatus::Processing->value)
            ->assertJsonPath('data.operation.child_count', 3);
        $operationId = $response->json('data.operation.public_id');

        $this->actingAs($owner)->postJson($this->confirmUrl($workspace, $family), $payload)
            ->assertAccepted()
            ->assertJsonPath('data.operation.public_id', $operationId);
        $this->assertDatabaseCount('document_family_deletion_operations', 1);
        $this->assertDatabaseCount('document_deletion_operations', 3);
        $this->assertSame(DocumentGovernanceStatus::Withdrawn, $versions[0]->fresh()->governance_status);
        $this->assertSame(DocumentGovernanceStatus::Withdrawn, $versions[1]->fresh()->governance_status);
        $this->assertSame(DocumentGovernanceStatus::Draft, $versions[2]->fresh()->governance_status);
        foreach ($versions as $version) {
            $this->assertSame(DocumentStatus::Deleting, $version->fresh()->status);
        }
    }

    public function test_reserved_export_hold_seam_blocks_before_any_mutation(): void
    {
        [$owner, $workspace, $family, $versions] = $this->familyWithVersions();
        $preview = $this->actingAs($owner)->postJson($this->previewUrl($workspace, $family));
        $this->app->instance(ExportSourceHold::class, new class implements ExportSourceHold
        {
            public function blocksPhysicalRemoval(Document $lockedDocument): bool
            {
                return true;
            }
        });

        $this->actingAs($owner)->postJson($this->confirmUrl($workspace, $family), [
            'confirmation_digest' => $preview->json('data.confirmation_digest'),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict();

        $this->assertDatabaseCount('document_family_deletion_operations', 0);
        $this->assertDatabaseCount('document_deletion_operations', 0);
        $this->assertSame(DocumentGovernanceStatus::Approved, $versions[0]->fresh()->governance_status);
    }

    public function test_open_clone_is_reported_and_blocks_confirmation(): void
    {
        [$owner, $workspace, $family, $versions] = $this->familyWithVersions();
        $sourceAttempt = IngestionEventClaim::factory()->for($versions[0])->create();
        $targetAttempt = IngestionEventClaim::factory()->for($versions[1])->create();
        $clone = DocumentContentCloneOperation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'source_document_id' => $versions[0]->id,
            'target_document_id' => $versions[1]->id,
            'source_ingestion_event_claim_id' => $sourceAttempt->id,
            'target_ingestion_event_claim_id' => $targetAttempt->id,
            'status' => DocumentContentCloneStatus::Authorised,
            'materialisation_pipeline_fingerprint' => str_repeat('a', 64),
            'materialisation_pipeline_components' => ['contract' => 'fixture-v1'],
            'source_checksum_sha256' => str_repeat('b', 64),
            'authorised_at' => now(),
        ]);

        $preview = $this->actingAs($owner)->postJson($this->previewUrl($workspace, $family))
            ->assertOk()
            ->assertJsonPath('data.blockers.open_clone_operations.0.public_id', $clone->public_id)
            ->assertJsonMissingPath('data.blockers.open_clone_operations.0.source_document_id');
        $this->actingAs($owner)->postJson($this->confirmUrl($workspace, $family), [
            'confirmation_digest' => $preview->json('data.confirmation_digest'),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict();

        $this->assertDatabaseCount('document_family_deletion_operations', 0);
        $this->assertDatabaseCount('document_deletion_operations', 0);
    }

    public function test_parent_converges_to_tombstone_or_partial_failure_from_children(): void
    {
        Queue::fake();
        [$owner, $workspace, $family] = $this->familyWithVersions();
        $preview = $this->actingAs($owner)->postJson($this->previewUrl($workspace, $family));
        $this->actingAs($owner)->postJson($this->confirmUrl($workspace, $family), [
            'confirmation_digest' => $preview->json('data.confirmation_digest'),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertAccepted();
        $operation = DocumentFamilyDeletionOperation::query()->firstOrFail();
        $firstChild = $operation->children()->firstOrFail();
        $firstChild->forceFill(['status' => DocumentDeletionStatus::Failed])->save();
        $this->assertSame(
            DocumentFamilyDeletionStatus::PartiallyFailed,
            app(ReconcileDocumentFamilyDeletion::class)->handle($operation->id)->status,
        );
        $this->assertNull($family->fresh()->tombstoned_at);

        $operation->children()->update(['status' => DocumentDeletionStatus::Completed, 'completed_at' => now()]);
        $this->assertSame(
            DocumentFamilyDeletionStatus::Completed,
            app(ReconcileDocumentFamilyDeletion::class)->handle($operation->id)->status,
        );
        $this->assertNotNull($family->fresh()->tombstoned_at);
        $this->actingAs($owner)->postJson($this->previewUrl($workspace, $family))->assertConflict();
    }

    /** @return array{User, Workspace, DocumentFamily, array<int, Document>} */
    private function familyWithVersions(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-29T12:00:00Z'));
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($owner)->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $current = Document::factory()->indexed()->approved()->for($workspace)->for($family, 'family')->for($owner, 'createdBy')->create([
            'effective_from' => now()->subYear(),
            'approved_at' => now()->subYear(),
        ]);
        $scheduled = Document::factory()->indexed()->approved()->for($workspace)->for($family, 'family')->for($owner, 'createdBy')->create([
            'predecessor_document_id' => $current->id,
            'effective_from' => now()->addMonth(),
            'approved_at' => now(),
        ]);
        $draft = Document::factory()->indexed()->for($workspace)->for($family, 'family')->for($owner, 'createdBy')->create([
            'predecessor_document_id' => $scheduled->id,
            'effective_from' => now()->addMonths(2),
        ]);

        return [$owner, $workspace, $family, [$current, $scheduled, $draft]];
    }

    private function previewUrl(Workspace $workspace, DocumentFamily $family): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-families/{$family->public_id}/deletion-preview";
    }

    private function confirmUrl(Workspace $workspace, DocumentFamily $family): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-families/{$family->public_id}/deletions";
    }
}
