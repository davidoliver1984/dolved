<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentFamily;
use App\Models\DocumentTag;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentMetadataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_read_workspace_metadata_but_only_administrators_can_mutate_it(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $category = DocumentCategory::factory()->for($workspace)->create(['name' => 'Clinical']);
        $tag = DocumentTag::factory()->for($workspace)->create(['name' => 'Current']);

        $this->actingAs($member)
            ->getJson($this->metadataUrl($workspace))
            ->assertOk()
            ->assertJsonPath('data.categories.0.public_id', $category->public_id)
            ->assertJsonPath('data.tags.0.public_id', $tag->public_id);

        $this->actingAs($member)
            ->getJson($this->familyUrl($workspace, $family))
            ->assertOk()
            ->assertJsonPath('data.capabilities.edit', false)
            ->assertJsonPath('data.edit_options', null);

        $this->actingAs($owner)
            ->getJson($this->familyUrl($workspace, $family))
            ->assertOk()
            ->assertJsonPath('data.capabilities.edit', true)
            ->assertJsonPath('data.edit_options.categories.0.public_id', $category->public_id)
            ->assertJsonPath('data.edit_options.tags.0.public_id', $tag->public_id)
            ->assertJsonPath('data.edit_options.owners.0.public_id', $owner->public_id);

        $this->actingAs($member)
            ->putJson($this->familyUrl($workspace, $family), $this->familyPayload($owner))
            ->assertForbidden();
    }

    public function test_administrator_updates_family_metadata_and_owner_through_their_separate_boundaries(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $newOwner = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($newOwner)->member()->create();
        $category = DocumentCategory::factory()->for($workspace)->create();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->putJson($this->familyUrl($workspace, $family), $this->familyPayload($owner, [
                'name' => 'Medication safety',
                'description' => 'Current medication governance documents.',
                'category_public_id' => $category->public_id,
                'review_due_date' => '2027-03-31',
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Medication safety')
            ->assertJsonPath('data.category.public_id', $category->public_id)
            ->assertJsonPath('data.owner.public_id', $owner->public_id)
            ->assertJsonPath('data.review_due_date', '2027-03-31');
        $this->actingAs($owner)
            ->patchJson($this->ownerUrl($workspace, $family), $this->ownerPayload($owner, $newOwner))
            ->assertOk()
            ->assertJsonPath('data.owner.public_id', $newOwner->public_id)
            ->assertJsonPath('data.owner_assignment_generation', 2);

        $this->assertDatabaseHas('document_governance_audit_events', [
            'document_family_id' => $family->id,
            'document_id' => null,
            'target_scope' => 'family',
            'action' => 'document_family_renamed',
        ]);
        $this->assertDatabaseHas('document_governance_audit_events', [
            'document_family_id' => $family->id,
            'action' => 'document_family_metadata_updated',
        ]);
    }

    public function test_owner_eligibility_is_checked_live_and_cross_workspace_identities_are_concealed(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $disabled = User::factory()->create(['disabled_at' => now()]);
        WorkspaceMembership::factory()->for($workspace)->for($disabled)->member()->create();
        $outsider = User::factory()->create();
        $disabledOutsider = User::factory()->create(['disabled_at' => now()]);
        $eligible = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($eligible)->member()->create();

        $this->actingAs($owner)
            ->patchJson($this->ownerUrl($workspace, $family), $this->ownerPayload($owner, $disabled))
            ->assertNotFound();
        $this->actingAs($owner)
            ->patchJson($this->ownerUrl($workspace, $family), $this->ownerPayload($owner, $outsider))
            ->assertNotFound();
        $this->actingAs($owner)
            ->patchJson($this->ownerUrl($workspace, $family), $this->ownerPayload($owner, $disabledOutsider))
            ->assertNotFound();
        $this->actingAs($owner)
            ->patchJson($this->ownerUrl($workspace, $family), $this->ownerPayload($owner, $eligible))
            ->assertOk()
            ->assertJsonPath('data.owner.public_id', $eligible->public_id);

        $otherWorkspace = Workspace::factory()->withOwner()->create();
        $otherCategory = DocumentCategory::factory()->for($otherWorkspace)->create();
        $otherTag = DocumentTag::factory()->for($otherWorkspace)->create();
        $this->actingAs($owner)
            ->getJson($this->familyUrl($workspace, DocumentFamily::factory()->for($otherWorkspace)->create()))
            ->assertNotFound();
        $this->actingAs($owner)
            ->putJson($this->familyUrl($workspace, $family), $this->familyPayload($owner, [
                'category_public_id' => $otherCategory->public_id,
            ]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->putJson($this->tagsUrl($workspace, $family), ['tag_public_ids' => [$otherTag->public_id]])
            ->assertNotFound();
    }

    public function test_tag_assignment_replaces_the_locked_final_set_and_rejects_more_than_twenty(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $tags = DocumentTag::factory()->count(21)->for($workspace)->create();

        $this->actingAs($owner)
            ->putJson($this->tagsUrl($workspace, $family), [
                'tag_public_ids' => $tags->take(20)->pluck('public_id')->all(),
            ])
            ->assertOk()
            ->assertJsonCount(20, 'data.tags');

        $this->actingAs($owner)
            ->putJson($this->tagsUrl($workspace, $family), [
                'tag_public_ids' => $tags->pluck('public_id')->all(),
            ])
            ->assertUnprocessable();

        $this->assertCount(20, $family->refresh()->tags);
    }

    public function test_two_contending_nineteen_plus_one_tag_sets_serialize_on_the_family_row(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create(['owner_user_id' => $owner->id]);
        $tags = DocumentTag::factory()->count(21)->for($workspace)->create();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $first = $tags->take(20)->pluck('public_id')->all();
        $second = $tags->take(19)->push($tags->last())->pluck('public_id')->all();
        $this->actingAs($owner)
            ->putJson($this->tagsUrl($workspace, $family), ['tag_public_ids' => $first])
            ->assertOk();
        $this->actingAs($owner)
            ->putJson($this->tagsUrl($workspace, $family), ['tag_public_ids' => $second])
            ->assertOk();

        $this->assertCount(20, $family->refresh()->tags);
        $actual = $family->tags->pluck('public_id')->sort()->values()->all();
        sort($second);
        $this->assertSame($second, $actual);
        if (DB::getDriverName() === 'pgsql') {
            $this->assertGreaterThanOrEqual(2, collect($queries)->filter(
                fn (string $sql): bool => str_contains($sql, 'document_families')
                    && str_contains($sql, 'for update'),
            )->count());
        }
    }

    public function test_taxonomy_mutations_are_workspace_scoped_and_categories_archive_without_deletion(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();

        $categoryId = $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspace->public_id}/document-categories", ['name' => ' Policies '])
            ->assertCreated()
            ->json('data.public_id');
        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$workspace->public_id}/document-categories/{$categoryId}", ['name' => 'Clinical policies'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Clinical policies');
        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$workspace->public_id}/document-categories/{$categoryId}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', DocumentCategoryStatus::Archived->value);
        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspace->public_id}/document-tags", ['name' => 'Governance'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Governance');

        $this->assertDatabaseHas('document_categories', [
            'workspace_id' => $workspace->id,
            'normalised_name' => 'clinical policies',
            'status' => 'archived',
        ]);
        $this->assertDatabaseHas('library_settings_audit_events', [
            'target_public_id' => $categoryId,
            'action' => 'document_category_renamed',
        ]);
        $this->assertDatabaseCount('library_settings_audit_events', 3);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create();

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function familyPayload(User $owner, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Policy family',
            'description' => null,
            'category_public_id' => null,
            'owner_public_id' => $owner->public_id,
            'review_due_date' => null,
        ], $overrides);
    }

    private function metadataUrl(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-metadata";
    }

    private function familyUrl(Workspace $workspace, DocumentFamily $family): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-families/{$family->public_id}/metadata";
    }

    private function tagsUrl(Workspace $workspace, DocumentFamily $family): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-families/{$family->public_id}/tags";
    }

    /** @return array<string, mixed> */
    private function ownerPayload(User $expected, User $intended): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'expected_owner_public_id' => $expected->public_id,
            'expected_owner_assignment_generation' => 1,
            'intended_owner_public_id' => $intended->public_id,
        ];
    }

    private function ownerUrl(Workspace $workspace, DocumentFamily $family): string
    {
        return "/api/workspaces/{$workspace->public_id}/document-families/{$family->public_id}/owner";
    }
}
