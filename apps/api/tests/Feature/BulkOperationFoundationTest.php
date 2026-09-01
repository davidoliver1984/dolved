<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BulkOperationStatus;
use App\Enums\BulkOperationType;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Models\BulkOperation;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\ImportBatch;
use App\Models\ImportDecisionSnapshot;
use App\Models\ImportItem;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BulkOperationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_bulk_relational_foundation_is_present(): void
    {
        foreach ([
            'bulk_operations',
            'bulk_operation_items',
            'bulk_operation_item_attempts',
            'bulk_operation_item_subordinate_transitions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}.");
        }
        $this->assertTrue(Schema::hasColumns('bulk_operation_items', [
            'operation_type', 'target_reference_status', 'target_kind',
            'expected_state_snapshot', 'eligibility_status', 'execution_status',
            'incorporated_attempt_generation',
        ]));
        $this->assertTrue(Schema::hasColumns('bulk_operation_item_attempts', [
            'attempt_ordinal', 'generation', 'attempt_token', 'lease_expires_at',
            'not_applied_reason', 'success_kind', 'result_digest',
        ]));
    }

    public function test_owner_freezes_current_page_approval_with_honest_preflight_and_deterministic_digest(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $eligible = Document::factory()->for($workspace)->indexed()->create();
        $excluded = Document::factory()->for($workspace)->indexed()->approved()->create();
        $key = (string) Str::uuid();
        $payload = [
            'operation_type' => BulkOperationType::Approval->value,
            'selection_mode' => 'current_page',
            'target_public_ids' => [$excluded->public_id, $eligible->public_id],
            'filters' => [],
            'payload' => [],
            'idempotency_key' => $key,
        ];

        $response = $this->actingAs($owner)->postJson($this->url($workspace), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_confirmation')
            ->assertJsonPath('data.counts.total', 2)
            ->assertJsonPath('data.counts.eligible', 1)
            ->assertJsonPath('data.counts.excluded', 1)
            ->assertJsonPath('data.exclusions.already_approved_or_current', 1);

        $publicId = $response->json('data.public_id');
        $digest = $response->json('data.membership_digest');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $digest);
        $this->assertDatabaseHas('bulk_operation_items', [
            'target_document_id' => $eligible->id,
            'eligibility_status' => 'eligible',
            'execution_status' => 'eligible',
        ]);
        $this->assertDatabaseHas('bulk_operation_items', [
            'target_document_id' => $excluded->id,
            'eligibility_status' => 'excluded',
            'execution_status' => 'excluded',
            'exclusion_reason' => 'already_approved_or_current',
        ]);

        $this->actingAs($owner)->getJson("{$this->url($workspace)}/{$publicId}")
            ->assertOk()->assertJsonPath('data.membership_digest', $digest);
        $this->actingAs($owner)->postJson($this->url($workspace), $payload)
            ->assertCreated()->assertJsonPath('data.public_id', $publicId)
            ->assertJsonPath('data.membership_digest', $digest);
        $this->assertDatabaseCount('bulk_operations', 1);
        $this->assertDatabaseCount('bulk_operation_items', 2);
    }

    public function test_idempotency_conflict_fails_closed_without_changing_membership(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $key = (string) Str::uuid();
        $request = [
            'operation_type' => 'bulk_approval',
            'selection_mode' => 'current_page',
            'target_public_ids' => [$document->public_id],
            'filters' => [],
            'payload' => [],
            'idempotency_key' => $key,
        ];
        $this->actingAs($owner)->postJson($this->url($workspace), $request)->assertCreated();
        $request['filters'] = ['historical' => true];
        $this->actingAs($owner)->postJson($this->url($workspace), $request)
            ->assertConflict()->assertJsonPath('message', 'The idempotency key is already bound to a different bulk request.');
        $this->assertDatabaseCount('bulk_operations', 1);
        $this->assertDatabaseCount('bulk_operation_items', 1);
    }

    public function test_all_filtered_membership_is_server_resolved_bounded_and_immutable(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $match = DocumentFamily::factory()->for($workspace)->create(['name' => 'Medication governance']);
        DocumentFamily::factory()->for($workspace)->create(['name' => 'Safeguarding']);
        $request = [
            'operation_type' => 'bulk_review_date_assignment',
            'selection_mode' => 'all_filtered',
            'target_public_ids' => [],
            'filters' => ['search' => 'Medication', 'historical' => true],
            'payload' => ['review_due_date' => '2027-01-15'],
            'idempotency_key' => (string) Str::uuid(),
        ];
        $response = $this->actingAs($owner)->postJson($this->url($workspace), $request)
            ->assertCreated()->assertJsonPath('data.counts.total', 1);
        $operation = BulkOperation::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        $this->assertSame($match->id, $operation->items->sole()->target_family_id);

        DocumentFamily::factory()->for($workspace)->create(['name' => 'Medication handling']);
        $this->assertCount(1, $operation->fresh()->items);

        config(['bulk_operations.max_targets' => 1]);
        $request['filters'] = ['historical' => true];
        $request['idempotency_key'] = (string) Str::uuid();
        $this->actingAs($owner)->postJson($this->url($workspace), $request)
            ->assertUnprocessable();
        $this->assertDatabaseCount('bulk_operations', 1);
    }

    public function test_members_cannot_create_bulk_operations_and_other_workspaces_are_concealed(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->member()->for($workspace)->for($member)->create();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $request = [
            'operation_type' => 'bulk_approval',
            'selection_mode' => 'current_page',
            'target_public_ids' => [$document->public_id],
            'filters' => [], 'payload' => [], 'idempotency_key' => (string) Str::uuid(),
        ];
        $this->actingAs($member)->postJson($this->url($workspace), $request)->assertForbidden();

        $created = $this->actingAs($owner)->postJson($this->url($workspace), $request)->assertCreated();
        [, $other] = $this->ownerWorkspace();
        $this->actingAs($other->creator)->getJson($this->url($other).'/'.$created->json('data.public_id'))->assertNotFound();

        $foreignDocument = Document::factory()->for($other)->indexed()->create();
        $request['target_public_ids'] = [$foreignDocument->public_id];
        $request['idempotency_key'] = (string) Str::uuid();
        $this->actingAs($owner)->postJson($this->url($workspace), $request)->assertNotFound();
    }

    public function test_every_v1_operation_has_a_frozen_typed_preflight_result(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $family = DocumentFamily::factory()->for($workspace)->create();
        $promotion = $this->promotionItem($workspace, $owner);

        $cases = [
            ['bulk_approval', $document->public_id, [], 'eligible', null],
            ['bulk_promotion', $promotion->public_id, [], 'eligible', null],
            ['bulk_applicability_change', $family->public_id, ['location_public_ids' => []], 'excluded', 'no_authoritative_predecessor'],
            ['bulk_owner_assignment', $family->public_id, ['owner_user_public_id' => (string) Str::uuid()], 'excluded', 'requested_owner_not_active_member'],
            ['bulk_category_assignment', $family->public_id, [], 'excluded', 'already_assigned'],
            ['bulk_tag_change', $family->public_id, ['mode' => 'replace', 'tag_public_ids' => []], 'excluded', 'add_remove_replace_no_op'],
            ['bulk_review_date_assignment', $family->public_id, ['review_due_date' => '2027-04-30'], 'eligible', null],
        ];

        foreach ($cases as [$operationType, $target, $operationPayload, $eligibility, $reason]) {
            $response = $this->actingAs($owner)->postJson($this->url($workspace), [
                'operation_type' => $operationType,
                'selection_mode' => 'current_page',
                'target_public_ids' => [$target],
                'filters' => [],
                'payload' => $operationPayload,
                'idempotency_key' => (string) Str::uuid(),
            ])->assertCreated();
            $response->assertJsonPath('data.items.0.eligibility_status', $eligibility)
                ->assertJsonPath('data.items.0.exclusion_reason', $reason);
        }

        $this->assertDatabaseCount('bulk_operations', 7);
        $this->assertDatabaseCount('bulk_operation_items', 7);
    }

    public function test_parent_bound_operation_discriminator_and_one_target_per_operation_are_structural(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->indexed()->create();
        $operation = $this->actingAs($owner)->postJson($this->url($workspace), [
            'operation_type' => 'bulk_approval', 'selection_mode' => 'current_page',
            'target_public_ids' => [$document->public_id], 'filters' => [], 'payload' => [],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $parent = BulkOperation::query()->where('public_id', $operation->json('data.public_id'))->firstOrFail();

        try {
            DB::table('bulk_operation_items')->insert([
                'bulk_operation_id' => $parent->id, 'workspace_id' => $workspace->id,
                'operation_type' => 'bulk_promotion', 'ordinal' => 2,
                'target_document_id' => $document->id, 'target_kind' => 'version',
                'target_public_id' => $document->public_id, 'target_display_label' => 'Mismatch',
                'expected_state_snapshot' => '{}', 'eligibility_status' => 'eligible',
                'execution_status' => 'eligible', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('A mismatched parent/item operation discriminator was accepted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertSame(BulkOperationStatus::AwaitingConfirmation, $parent->fresh()->status);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspace = Workspace::factory()->withOwner($owner)->create();

        return [$owner, $workspace];
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
        $definition = app(StructuredExtractionCanonicaliser::class)->canonicalValueBytes(['decision' => 'new_family']);
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

    private function url(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/bulk-operations";
    }
}
