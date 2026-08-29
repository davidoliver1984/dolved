<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentGovernanceStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentVersionGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_read_tenant_scoped_version_history_but_cannot_govern_it(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->member()->for($workspace)->for($member)->create();
        [$family, $first, $second] = $this->lineage($workspace, $owner);

        $this->actingAs($member)
            ->getJson($this->historyUrl($workspace, $family))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.public_id', $first->public_id)
            ->assertJsonPath('data.1.predecessor_public_id', $first->public_id);

        $this->actingAs($member)
            ->postJson($this->commandUrl($workspace, $second, 'approve'), ['idempotency_key' => (string) Str::uuid()])
            ->assertForbidden();

        $otherWorkspace = Workspace::factory()->withOwner()->create();
        $this->actingAs($owner)
            ->getJson($this->historyUrl($workspace, DocumentFamily::factory()->for($otherWorkspace)->create()))
            ->assertNotFound();
        $this->actingAs($owner)
            ->postJson($this->commandUrl($workspace, Document::factory()->for($otherWorkspace)->create(), 'approve'), ['idempotency_key' => (string) Str::uuid()])
            ->assertNotFound();
    }

    public function test_matching_completed_command_replays_and_conflicting_binding_fails_closed(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $admin = User::factory()->create();
        $adminMembership = WorkspaceMembership::factory()->admin()->for($workspace)->for($admin)->create();
        [, $first, $second] = $this->lineage($workspace, $owner);
        $key = (string) Str::uuid();

        $initial = $this->actingAs($owner)
            ->postJson($this->commandUrl($workspace, $first, 'approve'), ['idempotency_key' => $key])
            ->assertOk()
            ->assertJsonPath('data.governance_status', DocumentGovernanceStatus::Approved->value)
            ->assertJsonPath('meta.replayed', false);
        $commandPublicId = $initial->json('meta.command_public_id');

        $this->actingAs($owner)
            ->postJson($this->commandUrl($workspace, $first, 'approve'), ['idempotency_key' => $key])
            ->assertOk()
            ->assertJsonPath('meta.command_public_id', $commandPublicId)
            ->assertJsonPath('meta.replayed', true);

        $this->actingAs($owner)
            ->postJson($this->commandUrl($workspace, $second, 'approve'), ['idempotency_key' => $key])
            ->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_key_conflict');
        $this->actingAs($admin)
            ->postJson($this->commandUrl($workspace, $first, 'approve'), ['idempotency_key' => $key])
            ->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_key_conflict');

        $adminMembership->delete();
        $this->actingAs($admin)
            ->postJson($this->commandUrl($workspace, $first, 'approve'), ['idempotency_key' => $key])
            ->assertNotFound();

        $this->assertDatabaseCount('document_governance_commands', 1);
    }

    public function test_same_key_is_independent_across_purposes_and_invalid_state_is_typed(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        [, $first] = $this->lineage($workspace, $owner);
        $key = (string) Str::uuid();

        $this->actingAs($owner)
            ->postJson($this->commandUrl($workspace, $first, 'approve'), ['idempotency_key' => $key])
            ->assertOk();
        $this->actingAs($owner)
            ->postJson($this->commandUrl($workspace, $first, 'withdraw'), ['idempotency_key' => $key])
            ->assertOk()
            ->assertJsonPath('data.governance_status', DocumentGovernanceStatus::Withdrawn->value);
        $this->actingAs($owner)
            ->postJson($this->commandUrl($workspace, $first, 'approve'), ['idempotency_key' => (string) Str::uuid()])
            ->assertConflict()
            ->assertJsonPath('error.code', 'governance_state_conflict');

        $this->assertDatabaseCount('document_governance_commands', 2);
    }

    public function test_reschedule_and_owner_only_historical_correction_use_durable_commands(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $admin = User::factory()->create();
        WorkspaceMembership::factory()->admin()->for($workspace)->for($admin)->create();
        [, $first, $second] = $this->lineage($workspace, $owner);
        $this->travelTo('2026-01-02');
        $this->actingAs($owner)->postJson($this->commandUrl($workspace, $first, 'approve'), ['idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->travelTo('2026-02-01');
        $this->actingAs($owner)->postJson($this->commandUrl($workspace, $second, 'approve'), ['idempotency_key' => (string) Str::uuid()])->assertOk();

        $this->actingAs($admin)
            ->patchJson($this->commandUrl($workspace, $second, 'schedule'), [
                'idempotency_key' => (string) Str::uuid(),
                'effective_from' => '2026-07-01',
            ])
            ->assertOk()
            ->assertJsonPath('data.effective_from', '2026-07-01T00:00:00.000000Z');

        $rescheduleKey = (string) Str::uuid();
        $this->actingAs($admin)
            ->patchJson($this->commandUrl($workspace, $second, 'schedule'), [
                'idempotency_key' => $rescheduleKey,
                'effective_from' => '2026-08-01',
            ])
            ->assertOk();
        $this->actingAs($admin)
            ->patchJson($this->commandUrl($workspace, $second, 'schedule'), [
                'idempotency_key' => $rescheduleKey,
                'effective_from' => '2026-09-01',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_key_conflict');

        $correction = [
            'idempotency_key' => (string) Str::uuid(),
            'approved_at' => '2026-01-15T00:00:00Z',
            'withdrawn_at' => null,
            'reason' => 'Imported approval record',
        ];
        $this->actingAs($admin)
            ->patchJson($this->commandUrl($workspace, $first, 'timestamps'), $correction)
            ->assertForbidden();
        $this->actingAs($owner)
            ->patchJson($this->commandUrl($workspace, $first, 'timestamps'), $correction)
            ->assertOk()
            ->assertJsonPath('data.approved_at', '2026-01-15T00:00:00.000000Z');
    }

    public function test_command_target_is_structurally_bound_to_its_workspace(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        [$otherOwner, $otherWorkspace] = $this->ownerWorkspace();
        $foreignDocument = Document::factory()->for($otherWorkspace)->for($otherOwner, 'createdBy')->create();

        $this->expectException(QueryException::class);
        DB::table('document_governance_commands')->insert([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'purpose' => 'approve',
            'idempotency_key' => (string) Str::uuid(),
            'actor_user_id' => $owner->id,
            'target_kind' => 'document_version',
            'target_document_id' => $foreignDocument->id,
            'target_state_at_creation' => 'draft',
            'request_payload_digest' => str_repeat('a', 64),
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create();

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }

    /** @return array{DocumentFamily, Document, Document} */
    private function lineage(Workspace $workspace, User $creator): array
    {
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $creator->id]);
        $first = Document::factory()->for($workspace)->for($family, 'family')->for($creator, 'createdBy')->create([
            'effective_from' => CarbonImmutable::parse('2026-01-01'),
        ]);
        $second = Document::factory()->for($workspace)->for($family, 'family')->for($creator, 'createdBy')->create([
            'predecessor_document_id' => $first->id,
            'effective_from' => CarbonImmutable::parse('2026-06-01'),
        ]);

        return [$family, $first, $second];
    }

    private function historyUrl(Workspace $workspace, DocumentFamily $family): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-families/{$family->public_id}/versions";
    }

    private function commandUrl(Workspace $workspace, Document $document, string $command): string
    {
        return "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}/governance/{$command}";
    }
}
