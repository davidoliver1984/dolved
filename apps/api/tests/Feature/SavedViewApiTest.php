<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SavedView;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SavedViewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_open_rename_and_delete_a_live_saved_view(): void
    {
        [$user, $workspace] = $this->ownerWorkspace();
        $definition = [
            'historical' => true,
            'page_size' => 50,
            'direction' => 'asc',
            'sort' => 'title',
            'filters' => ['status' => 'indexed'],
            'search' => 'medication',
        ];

        $publicId = $this->actingAs($user)
            ->postJson($this->collectionUrl($workspace), [
                'name' => ' Current policies ',
                'definition' => $definition,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Current policies')
            ->assertJsonPath('data.definition.search', 'medication')
            ->assertJsonPath('data.notices', [])
            ->json('data.public_id');

        $this->actingAs($user)
            ->getJson($this->itemUrl($workspace, $publicId))
            ->assertOk()
            ->assertJsonPath('data.definition_schema_version', 1)
            ->assertJsonPath('data.definition.page_size', 50);

        $this->actingAs($user)
            ->patchJson($this->itemUrl($workspace, $publicId), ['name' => 'My policies'])
            ->assertOk()
            ->assertJsonPath('data.name', 'My policies');

        $this->actingAs($user)
            ->deleteJson($this->itemUrl($workspace, $publicId))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('saved_views', ['public_id' => $publicId]);
        $this->assertDatabaseCount('library_settings_audit_events', 3);
    }

    public function test_definition_is_strict_bounded_and_names_are_normalised_per_owner(): void
    {
        [$user, $workspace] = $this->ownerWorkspace();

        $this->actingAs($user)
            ->postJson($this->collectionUrl($workspace), [
                'name' => 'Clinical',
                'definition' => ['search' => 'policy', 'unsupported' => true],
            ])
            ->assertUnprocessable();
        $this->actingAs($user)
            ->postJson($this->collectionUrl($workspace), [
                'name' => 'Clinical',
                'definition' => ['filters' => ['status' => 'not-a-status']],
            ])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->postJson($this->collectionUrl($workspace), [
                'name' => 'Clinical',
                'definition' => ['search' => 'policy'],
            ])
            ->assertCreated();
        $this->actingAs($user)
            ->postJson($this->collectionUrl($workspace), [
                'name' => " clinical\t",
                'definition' => ['search' => 'different'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_cross_user_and_cross_workspace_saved_views_are_concealed(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $otherWorkspace = Workspace::factory()->withOwner($owner)->create();
        $view = $this->savedView($workspace, $owner, 'Owner only');
        $otherView = $this->savedView($otherWorkspace, $owner, 'Other workspace');

        $this->actingAs($member)->getJson($this->itemUrl($workspace, $view->public_id))->assertNotFound();
        $this->actingAs($member)->patchJson($this->itemUrl($workspace, $view->public_id), ['name' => 'No'])->assertNotFound();
        $this->actingAs($member)->deleteJson($this->itemUrl($workspace, $view->public_id))->assertNotFound();
        $this->actingAs($owner)->getJson($this->itemUrl($workspace, $otherView->public_id))->assertNotFound();
    }

    public function test_membership_end_removes_views_but_disabled_user_views_remain_inert(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        $membership = WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $view = $this->savedView($workspace, $member, 'My view');

        $member->disabled_at = now();
        $member->save();
        $this->assertDatabaseHas('saved_views', ['id' => $view->id]);

        $membership->delete();
        $this->assertDatabaseMissing('saved_views', ['id' => $view->id]);
    }

    public function test_unsupported_stored_fields_are_dropped_on_open_with_a_visible_notice(): void
    {
        [$user, $workspace] = $this->ownerWorkspace();
        $view = $this->savedView($workspace, $user, 'Legacy');
        DB::table('saved_views')->where('id', $view->id)->update([
            'definition' => json_encode(['search' => 'policy', 'old_filter' => 'legacy'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($user)
            ->getJson($this->itemUrl($workspace, $view->public_id))
            ->assertOk()
            ->assertJsonPath('data.definition.search', 'policy')
            ->assertJsonMissingPath('data.definition.old_filter')
            ->assertJsonPath('data.notices.0', "Unsupported saved-view field 'old_filter' was removed.");
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create();

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }

    private function savedView(Workspace $workspace, User $user, string $name): SavedView
    {
        $view = new SavedView([
            'name' => $name,
            'definition_schema_version' => 1,
            'definition' => ['search' => 'policy'],
        ]);
        $view->workspace()->associate($workspace);
        $view->user()->associate($user);
        $view->save();

        return $view;
    }

    private function collectionUrl(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/saved-views";
    }

    private function itemUrl(Workspace $workspace, string $publicId): string
    {
        return $this->collectionUrl($workspace)."/{$publicId}";
    }
}
