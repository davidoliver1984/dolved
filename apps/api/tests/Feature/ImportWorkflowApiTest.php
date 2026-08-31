<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\Documents\ImportStagingStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImportWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_a_private_import_batch_and_resume_it(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $storage = $this->mock(ImportStagingStorage::class);
        $storage->shouldReceive('createUploadRequest')->twice()->andReturn([
            'url' => 'http://object-storage.test/staged',
            'method' => 'PUT',
            'headers' => ['Content-Type' => 'application/pdf'],
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);

        $response = $this->actingAs($user)->postJson($this->importsUrl($workspace), [
            'files' => [
                ['filename' => 'Medication policy.pdf', 'media_type' => 'application/pdf', 'size_bytes' => 1200],
                ['filename' => 'Safeguarding policy.pdf', 'media_type' => 'application/pdf', 'size_bytes' => 2400],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.batch.items')
            ->assertJsonCount(2, 'data.uploads')
            ->assertJsonMissingPath('data.batch.items.0.staged_object_key')
            ->assertJsonMissingPath('data.batch.workspace_id');
        $batchId = $response->json('data.batch.public_id');

        $this->actingAs($user)->getJson("{$this->importsUrl($workspace)}/{$batchId}")
            ->assertOk()->assertJsonPath('data.public_id', $batchId)
            ->assertJsonPath('data.items.0.preflight_status', 'pending');
        $this->assertDatabaseCount('import_batches', 1);
        $this->assertDatabaseCount('import_items', 2);
    }

    public function test_configuration_is_bounded_and_cross_workspace_batches_are_concealed(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        [, $other] = $this->memberWorkspace();
        $batch = ImportBatch::query()->create([
            'public_id' => fake()->uuid(),
            'workspace_id' => $other->id,
            'initiated_by_user_id' => $other->created_by_user_id,
            'status' => ImportBatchStatus::Open,
            'retention_expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)->getJson("{$this->importsUrl($workspace)}/configuration")
            ->assertOk()
            ->assertJsonPath('data.retention_days', 7)
            ->assertJsonMissingPath('data.staging_disk')
            ->assertJsonMissingPath('data.review_options.owners.0.email');
        $this->actingAs($user)->getJson("{$this->importsUrl($workspace)}/{$batch->public_id}")
            ->assertNotFound();
    }

    public function test_invalid_files_fail_before_any_import_identity_is_created(): void
    {
        [$user, $workspace] = $this->memberWorkspace();

        $this->actingAs($user)->postJson($this->importsUrl($workspace), [
            'files' => [['filename' => 'unsafe.csv', 'media_type' => 'text/csv', 'size_bytes' => 10]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['files.0.media_type']);

        $this->assertDatabaseCount('import_batches', 0);
        $this->assertDatabaseCount('import_items', 0);
    }

    /** @return array{User, Workspace} */
    private function memberWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by_user_id' => $user->id]);
        WorkspaceMembership::factory()->for($workspace)->for($user)->member()->create();

        return [$user, $workspace];
    }

    private function importsUrl(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/imports";
    }
}
