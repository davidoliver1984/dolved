<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\BulkOperations\ClaimBulkOperationItem;
use App\Actions\BulkOperations\ExecuteBulkOperationItem;
use App\Actions\BulkOperations\FinalizeBulkOperationAttempt;
use App\Actions\BulkOperations\ProcessBulkOperation;
use App\Actions\BulkOperations\ReclaimExpiredBulkAttempts;
use App\Enums\BulkAttemptStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Enums\PromotionAttemptStatus;
use App\Jobs\ExecuteBulkOperation;
use App\Models\BulkOperation;
use App\Models\Document;
use App\Models\ImportBatch;
use App\Models\ImportDecisionSnapshot;
use App\Models\ImportItem;
use App\Models\PromotionAttempt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BulkOperationExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_is_actor_bound_audited_idempotent_and_dispatches_exact_operation(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, [$document]);

        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')
            ->assertOk()->assertJsonPath('data.status', 'queued');
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')
            ->assertOk()->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(ExecuteBulkOperation::class, 2);
        $this->assertDatabaseCount('bulk_operation_audit_events', 1);
        $this->assertDatabaseHas('bulk_operation_audit_events', ['event_type' => 'bulk_operation.confirmed']);
    }

    public function test_database_local_item_executes_once_and_parent_converges_with_complete_audit_chain(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, [$document]);
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();

        app(ProcessBulkOperation::class)->handle($operation->refresh());

        $this->assertSame('approved', $document->refresh()->governance_status->value);
        $this->assertSame('succeeded', $operation->items()->sole()->execution_status->value);
        $this->assertSame(1, $operation->items()->sole()->incorporated_attempt_generation);
        $this->assertSame('completed', $operation->refresh()->status->value);
        $this->assertDatabaseHas('bulk_operation_item_attempts', [
            'bulk_operation_item_id' => $operation->items()->sole()->id,
            'generation' => 1,
            'status' => 'succeeded',
            'success_kind' => 'database_local',
        ]);
        $this->assertDatabaseHas('bulk_operation_audit_events', ['event_type' => 'bulk_operation.item_finalized']);
        $this->assertDatabaseHas('bulk_operation_audit_events', ['event_type' => 'bulk_operation.converged']);
    }

    public function test_changed_expected_state_is_not_applied_and_never_retried(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, [$document]);
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();
        $document->forceFill(['effective_from' => now()->addDay()])->save();

        app(ProcessBulkOperation::class)->handle($operation->refresh());

        $this->assertSame('draft', $document->refresh()->governance_status->value);
        $this->assertSame('skipped', $operation->items()->sole()->execution_status->value);
        $this->assertSame('expected_state_mismatch', $operation->items()->sole()->terminal_reason);
        $this->assertDatabaseHas('bulk_operation_item_attempts', ['status' => 'not_applied']);
        $this->assertDatabaseCount('bulk_operation_item_attempts', 1);
    }

    public function test_terminal_unincorporated_generation_blocks_next_claim_until_finalized(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, [$document]);
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();
        $claim = app(ClaimBulkOperationItem::class);
        $first = $claim->handle($operation->refresh(), 'test-worker');
        $this->assertNotNull($first);
        $first->forceFill([
            'status' => BulkAttemptStatus::FailedRetryable,
            'failure_category' => 'execution_failed',
            'completed_at' => now(),
        ])->save();

        $this->assertNull($claim->handle($operation->refresh(), 'second-worker'));
        app(FinalizeBulkOperationAttempt::class)->handle($first);
        $second = $claim->handle($operation->refresh(), 'second-worker');
        $this->assertNotNull($second);
        $this->assertSame(2, $second->generation);
    }

    public function test_expired_attempt_is_abandoned_and_incorporated_before_retry_generation_opens(): void
    {
        Queue::fake();
        config(['bulk_operations.attempt_lease_seconds' => -1]);
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, [$document]);
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();
        $claim = app(ClaimBulkOperationItem::class);
        $first = $claim->handle($operation->refresh(), 'dead-worker');

        $this->assertSame(1, app(ReclaimExpiredBulkAttempts::class)->handle());
        $this->assertSame('abandoned', $first->refresh()->status->value);
        $this->assertSame(1, $operation->items()->sole()->refresh()->incorporated_attempt_generation);
        config(['bulk_operations.attempt_lease_seconds' => 120]);
        $second = $claim->handle($operation->refresh(), 'replacement-worker');
        $this->assertSame(2, $second?->generation);
    }

    public function test_cancellation_stops_unattempted_items_but_does_not_rewrite_open_attempt(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $documents = Document::factory()->count(2)->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, $documents->all());
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();
        $attempt = app(ClaimBulkOperationItem::class)->handle($operation->refresh(), 'active-worker');

        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/cancel')
            ->assertOk()->assertJsonPath('data.cancellation_requested_at', fn ($value): bool => is_string($value));

        $this->assertSame('open', $attempt?->refresh()->status->value);
        $this->assertSame(1, $operation->items()->where('execution_status', 'cancelled')->count());
        $this->assertSame(1, $operation->items()->where('execution_status', 'eligible')->count());
    }

    public function test_cancellation_converges_a_later_retryable_failure_without_opening_another_generation(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, [$document]);
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();
        $attempt = app(ClaimBulkOperationItem::class)->handle($operation->refresh(), 'active-worker');
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/cancel')->assertOk();
        $attempt?->forceFill([
            'status' => BulkAttemptStatus::FailedRetryable,
            'failure_category' => 'execution_failed',
            'completed_at' => now(),
        ])->save();
        app(FinalizeBulkOperationAttempt::class)->handle($attempt);

        app(ProcessBulkOperation::class)->handle($operation->refresh());

        $this->assertSame('failed_permanent', $operation->items()->sole()->execution_status->value);
        $this->assertSame('cancellation_requested', $operation->items()->sole()->terminal_reason);
        $this->assertSame('cancelled_after_partial_execution', $operation->refresh()->status->value);
        $this->assertDatabaseCount('bulk_operation_item_attempts', 1);
    }

    public function test_stale_worker_cannot_mutate_after_its_generation_was_abandoned(): void
    {
        Queue::fake();
        config(['bulk_operations.attempt_lease_seconds' => -1]);
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->createApproval($owner, $workspace, [$document]);
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();
        $stale = app(ClaimBulkOperationItem::class)->handle($operation->refresh(), 'stale-worker');
        app(ReclaimExpiredBulkAttempts::class)->handle();

        app(ExecuteBulkOperationItem::class)->handle($stale);

        $this->assertSame('draft', $document->refresh()->governance_status->value);
        $this->assertSame('abandoned', $stale?->refresh()->status->value);
        $this->assertDatabaseCount('bulk_operation_item_attempts', 1);
    }

    public function test_promotion_subordinate_is_recorded_then_reconciled_from_its_terminal_truth(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $target = $this->promotionItem($workspace, $owner);
        $response = $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/bulk-operations", [
            'operation_type' => 'bulk_promotion',
            'selection_mode' => 'current_page',
            'target_public_ids' => [$target->public_id],
            'filters' => [],
            'payload' => [],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $operation = BulkOperation::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        $this->actingAs($owner)->postJson($this->url($workspace, $operation).'/confirm')->assertOk();

        app(ProcessBulkOperation::class)->handle($operation->refresh());

        $item = $operation->items()->sole();
        $this->assertSame('waiting_on_subordinate', $item->execution_status->value);
        $this->assertSame('promotion_attempt', $item->subordinate_kind->value);
        $this->assertSame(1, $item->incorporated_attempt_generation);
        $this->assertDatabaseHas('bulk_operation_item_subordinate_transitions', [
            'bulk_operation_item_id' => $item->id,
            'transition_category' => 'initiated',
        ]);

        PromotionAttempt::query()->where('public_id', $item->subordinate_identity_value)->firstOrFail()
            ->forceFill(['status' => PromotionAttemptStatus::Conflict])->save();
        app(ProcessBulkOperation::class)->handle($operation->refresh());

        $this->assertSame('failed_permanent', $item->refresh()->execution_status->value);
        $this->assertSame('promotion_conflict', $item->terminal_reason);
        $this->assertSame('completed_with_exceptions', $operation->refresh()->status->value);
        $this->assertDatabaseCount('bulk_operation_item_attempts', 1);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspace = Workspace::factory()->withOwner($owner)->create();

        return [$owner, $workspace];
    }

    /** @param list<Document> $documents */
    private function createApproval(User $owner, Workspace $workspace, array $documents): BulkOperation
    {
        $response = $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->public_id}/bulk-operations", [
            'operation_type' => 'bulk_approval',
            'selection_mode' => 'current_page',
            'target_public_ids' => array_map(fn (Document $document): string => $document->public_id, $documents),
            'filters' => [],
            'payload' => [],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        return BulkOperation::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();
    }

    private function promotionItem(Workspace $workspace, User $actor): ImportItem
    {
        $batch = ImportBatch::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'initiated_by_user_id' => $actor->id,
            'status' => ImportBatchStatus::Open,
            'retention_expires_at' => now()->addDays(7),
        ]);
        $publicId = (string) Str::uuid();
        $item = ImportItem::query()->create([
            'public_id' => $publicId,
            'import_batch_id' => $batch->id,
            'workspace_id' => $workspace->id,
            'staged_object_key' => "imports/workspaces/{$workspace->public_id}/items/{$publicId}/source",
            'source_filename' => 'promotion-ready.pdf',
            'source_checksum_sha256' => str_repeat('a', 64),
            'media_type' => 'application/pdf',
            'size_bytes' => 512,
            'preflight_status' => ImportPreflightStatus::Verified,
            'match_status' => ImportMatchStatus::Resolved,
        ]);
        $definition = app(StructuredExtractionCanonicaliser::class)
            ->canonicalValueBytes(['decision' => 'new_family']);
        $snapshot = ImportDecisionSnapshot::query()->create([
            'public_id' => (string) Str::uuid(),
            'import_item_id' => $item->id,
            'schema_version' => 1,
            'canonical_definition' => $definition,
            'digest_sha256' => hash('sha256', $definition),
            'actor_user_id' => $actor->id,
        ]);
        $item->update(['current_decision_snapshot_id' => $snapshot->id]);

        return $item->refresh();
    }

    private function url(Workspace $workspace, BulkOperation $operation): string
    {
        return "/api/workspaces/{$workspace->public_id}/bulk-operations/{$operation->public_id}";
    }
}
