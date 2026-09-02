<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\DetectStuckOrFailedDocumentDeletions;
use App\Actions\Documents\FanOutUserDisablement;
use App\Actions\Documents\ProjectDocumentGovernanceEvent;
use App\Actions\Documents\ReconcileOwnershipEligibility;
use App\Actions\Documents\SweepLegacyOwnershipEligibility;
use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentGovernanceEventKey;
use App\Jobs\FanOutUserDisablementReconciliation;
use App\Jobs\ProjectDocumentGovernanceNotifications;
use App\Jobs\ReconcileOwnershipEligibilityAfterMembershipChange;
use App\Models\Document;
use App\Models\DocumentDeletionOperation;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceEventProjection;
use App\Models\DocumentGovernanceNotification;
use App\Models\DocumentGovernanceNotificationProjectionReceipt;
use App\Models\OwnershipEligibilityReconciliation;
use App\Models\User;
use App\Models\UserDisablementReconciliationSource;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentGovernanceNotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_event_vocabulary_is_closed_and_exactly_twenty_entries(): void
    {
        $this->assertCount(20, DocumentGovernanceEventKey::cases());
        $this->assertSame([
            'import.batch.completed',
            'import.batch.completed_with_exceptions',
            'import.item.processing_failed',
            'import.item.requires_user_action',
            'import.item.match_ambiguous',
            'governance.version.approved',
            'promotion.completed',
            'promotion.failed',
            'governance.authority.approaching',
            'governance.authority.attained',
            'governance.authority.blocked',
            'governance.review.due_soon',
            'governance.review.overdue',
            'governance.ownership.reassignment_required',
            'applicability.successor.completed',
            'applicability.successor.failed',
            'bulk_operation.completed',
            'bulk_operation.completed_with_exceptions',
            'bulk_operation.failed_before_execution',
            'deletion.operation.stuck_or_failed',
        ], array_map(fn (DocumentGovernanceEventKey $key): string => $key->value, DocumentGovernanceEventKey::cases()));
    }

    public function test_occurrence_identity_is_idempotent_and_dispatches_only_the_bound_event(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $record = app(RecordDocumentGovernanceEvent::class);

        $first = $record->record($workspace, DocumentGovernanceEventKey::ImportBatchCompleted, 'batch-1', 'batch-1', [
            'initiating_user_public_id' => $owner->public_id,
        ]);
        $second = $record->record($workspace, DocumentGovernanceEventKey::ImportBatchCompleted, 'batch-1', 'batch-1', [
            'initiating_user_public_id' => $owner->public_id,
        ]);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('document_governance_events', 1);
        Queue::assertPushed(ProjectDocumentGovernanceNotifications::class, 1);
    }

    public function test_projection_resolves_current_recipients_and_replay_is_idempotent(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $event = app(RecordDocumentGovernanceEvent::class)->record(
            $workspace,
            DocumentGovernanceEventKey::GovernanceReviewOverdue,
            $family->public_id,
            "{$family->public_id}:2026-09-01:overdue",
            [
                'document_family_public_id' => $family->public_id,
                'target_kind' => 'family',
                'target_public_id' => $family->public_id,
                'target_display_label' => $family->name,
            ],
        );

        $project = app(ProjectDocumentGovernanceEvent::class);
        $project->handle($event);
        $project->handle($event->refresh());

        $this->assertDatabaseCount('document_governance_event_projections', 1);
        $this->assertDatabaseCount('document_governance_notification_projection_receipts', 1);
        $this->assertDatabaseCount('document_governance_notifications', 1);
        $notification = DocumentGovernanceNotification::query()->sole();
        $this->assertSame($owner->public_id, $notification->recipient_user_public_id);
        $this->assertSame('warning', $notification->severity);
        $this->assertNotNull($event->refresh()->published_at);
    }

    public function test_authority_loss_after_resolution_suppresses_delivery_without_cross_workspace_fallback(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $event = app(RecordDocumentGovernanceEvent::class)->record(
            $workspace,
            DocumentGovernanceEventKey::BulkOperationCompleted,
            'bulk-1',
            'bulk-1',
            ['initiating_user_public_id' => $owner->public_id],
        );
        $projection = DocumentGovernanceEventProjection::query()->create([
            'workspace_id' => $workspace->id,
            'source_event_id' => $event->event_id,
            'state' => 'projecting',
            'resolved_recipient_set_digest' => hash('sha256', json_encode([$owner->public_id], JSON_THROW_ON_ERROR)),
            'started_at' => now(),
        ]);
        DocumentGovernanceNotificationProjectionReceipt::query()->create([
            'workspace_id' => $workspace->id,
            'event_projection_id' => $projection->id,
            'recipient_user_public_id' => $owner->public_id,
            'outcome' => 'pending',
        ]);
        WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('user_id', $owner->id)->delete();

        app(ProjectDocumentGovernanceEvent::class)->handle($event);

        $this->assertDatabaseCount('document_governance_notifications', 0);
        $this->assertDatabaseHas('document_governance_notification_projection_receipts', [
            'event_projection_id' => $projection->id,
            'outcome' => 'suppressed',
            'suppression_reason' => 'membership_removed',
        ]);
    }

    public function test_membership_loss_reconciliation_emits_one_event_per_owned_family_and_replays_safely(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $work = OwnershipEligibilityReconciliation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'affected_user_public_id' => $owner->public_id,
            'membership_public_id' => WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('user_id', $owner->id)->value('public_id'),
            'eligibility_loss_cause_identity' => (string) Str::uuid(),
        ]);

        $reconcile = app(ReconcileOwnershipEligibility::class);
        $reconcile->handle($work);
        $reconcile->handle($work->refresh());

        $this->assertDatabaseCount('document_governance_events', 1);
        $this->assertDatabaseHas('document_governance_events', [
            'workspace_id' => $workspace->id,
            'event_key' => DocumentGovernanceEventKey::GovernanceOwnershipReassignmentRequired->value,
            'correlation_id' => $family->public_id,
        ]);
        $this->assertNotNull($work->refresh()->completed_at);
        Queue::assertPushed(ProjectDocumentGovernanceNotifications::class, 1);
    }

    public function test_user_disablement_creates_durable_reconciliation_source_and_work(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($otherWorkspace)->for($owner)->member()->create();
        DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        DocumentFamily::factory()->for($otherWorkspace)->create(['owner_user_id' => $owner->id]);

        $owner->forceFill(['disabled_at' => now()])->save();

        $this->assertDatabaseHas('user_disablement_reconciliation_sources', ['user_id' => $owner->id]);
        $this->assertDatabaseCount('ownership_eligibility_reconciliations', 0);
        Queue::assertPushed(FanOutUserDisablementReconciliation::class, 1);

        $source = UserDisablementReconciliationSource::query()->sole();
        app(FanOutUserDisablement::class)->handle($source, 1);
        $this->assertNull($source->refresh()->completed_at);
        $this->assertDatabaseCount('ownership_eligibility_reconciliations', 1);

        app(FanOutUserDisablement::class)->handle($source, 1);
        app(FanOutUserDisablement::class)->handle($source, 1);

        $this->assertNotNull($source->refresh()->completed_at);
        $this->assertDatabaseCount('ownership_eligibility_reconciliations', 2);
        $this->assertDatabaseHas('ownership_eligibility_reconciliations', [
            'workspace_id' => $workspace->id,
            'affected_user_public_id' => $owner->public_id,
            'eligibility_loss_cause_identity' => $source->public_id,
        ]);
        $this->assertDatabaseHas('ownership_eligibility_reconciliations', [
            'workspace_id' => $otherWorkspace->id,
            'affected_user_public_id' => $owner->public_id,
            'eligibility_loss_cause_identity' => $source->public_id,
        ]);
        Queue::assertPushed(ReconcileOwnershipEligibilityAfterMembershipChange::class, 2);
    }

    public function test_owner_change_api_advances_generation_and_replays_without_a_second_audit(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $newOwner = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($newOwner)->member()->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'expected_owner_public_id' => $owner->public_id,
            'expected_owner_assignment_generation' => 1,
            'intended_owner_public_id' => $newOwner->public_id,
        ];
        $url = "/api/workspaces/{$workspace->public_id}/document-families/{$family->public_id}/owner";

        $this->actingAs($owner)->patchJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.owner.public_id', $newOwner->public_id)
            ->assertJsonPath('data.owner_assignment_generation', 2);
        $this->actingAs($owner)->patchJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.owner_assignment_generation', 2);

        $this->assertDatabaseCount('document_governance_commands', 1);
        $this->assertDatabaseCount('document_governance_audit_events', 1);
        $this->assertDatabaseHas('document_governance_audit_events', [
            'document_family_id' => $family->id,
            'action' => 'document_family_owner_changed',
        ]);
    }

    public function test_legacy_owner_sweep_uses_assignment_generation_and_replays_safely(): void
    {
        Queue::fake();
        $workspace = Workspace::factory()->create();
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $firstOwner->id,
            'owner_assignment_generation' => 1,
        ]);

        $sweep = app(SweepLegacyOwnershipEligibility::class);
        $sweep->handle();
        $sweep->handle();
        $this->assertDatabaseCount('document_governance_events', 1);
        $this->assertDatabaseHas('document_governance_events', [
            'occurrence_key' => "{$family->public_id}:1:{$firstOwner->public_id}",
        ]);

        $family->forceFill([
            'owner_user_id' => $secondOwner->id,
            'owner_assignment_generation' => 2,
        ])->save();
        $sweep->handle();

        $this->assertDatabaseCount('document_governance_events', 2);
        $this->assertDatabaseHas('document_governance_events', [
            'occurrence_key' => "{$family->public_id}:2:{$secondOwner->public_id}",
        ]);
    }

    public function test_deletion_detector_distinguishes_stuck_and_permanent_failure_within_one_generation(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = DocumentDeletionOperation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
            'requested_by_user_id' => $owner->id,
            'correlation_id' => (string) Str::uuid(),
            'status' => DocumentDeletionStatus::Queued,
            'active_attempt_ids' => [],
            'lease_generation' => 3,
        ]);
        DocumentDeletionOperation::query()->whereKey($operation->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);

        $detect = app(DetectStuckOrFailedDocumentDeletions::class);
        $this->assertSame(1, $detect->handle());
        $this->assertSame(0, $detect->handle());

        $operation->refresh()->forceFill(['status' => DocumentDeletionStatus::Failed])->save();
        $this->assertSame(1, $detect->handle());
        $this->assertSame(0, $detect->handle());

        $this->assertDatabaseCount('document_governance_events', 2);
        $this->assertDatabaseHas('document_governance_events', [
            'occurrence_key' => "{$operation->public_id}:3:stuck",
        ]);
        $this->assertDatabaseHas('document_governance_events', [
            'occurrence_key' => "{$operation->public_id}:3:failed_permanent",
        ]);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create();

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }
}
