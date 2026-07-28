<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\Documents\DocumentObjectStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DocumentUploadWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_member_can_read_upload_configuration(): void
    {
        [$user, $workspace] = $this->memberWorkspace();

        $this->actingAs($user)
            ->getJson($this->configurationUrl($workspace))
            ->assertOk()
            ->assertJsonPath('data.max_upload_bytes', 25 * 1024 * 1024)
            ->assertJsonPath('data.upload_concurrency', 3)
            ->assertJsonPath('data.formats.pdf.0', 'application/pdf')
            ->assertJsonMissingPath('data.storage_disk');
    }

    public function test_every_active_workspace_role_can_initialise_an_upload(): void
    {
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('createUploadRequest')
            ->times(3)
            ->andReturn($this->signedUpload());

        foreach (WorkspaceRole::cases() as $role) {
            [$user, $workspace] = $this->memberWorkspace($role);

            $this->actingAs($user)
                ->postJson($this->initialiseUrl($workspace), $this->metadata())
                ->assertCreated()
                ->assertJsonPath(
                    'data.document.status',
                    DocumentStatus::Uploading->value,
                );
        }
    }

    public function test_initialisation_creates_uploading_document_and_safe_presigned_response(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('createUploadRequest')
            ->once()
            ->withArgs(function (Document $document) use ($workspace): bool {
                return $document->status === DocumentStatus::Uploading
                    && $document->workspace->is($workspace)
                    && str_starts_with(
                        $document->storage_key,
                        "workspaces/{$workspace->public_id}/documents/",
                    )
                    && str_ends_with($document->storage_key, '/source.pdf')
                    && ! str_contains($document->storage_key, 'Quarterly Report');
            })
            ->andReturn($this->signedUpload());

        $response = $this->actingAs($user)
            ->postJson($this->initialiseUrl($workspace), $this->metadata());

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'document' => [
                        'public_id',
                        'source_filename',
                        'media_type',
                        'size_bytes',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                    'upload' => [
                        'url',
                        'method',
                        'headers',
                        'expires_at',
                    ],
                ],
            ])
            ->assertJsonPath('data.upload.method', 'PUT')
            ->assertJsonPath('data.upload.headers.Content-Type', 'application/pdf')
            ->assertJsonMissingPath('data.document.storage_key')
            ->assertJsonMissingPath('data.bucket')
            ->assertJsonMissingPath('data.disk');

        $this->assertDatabaseHas('documents', [
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $user->id,
            'status' => DocumentStatus::Uploading->value,
            'source_filename' => 'Quarterly Report.pdf',
            'size_bytes' => 42_000,
        ]);
    }

    public function test_initialisation_requires_authentication_and_verification(): void
    {
        [, $workspace] = $this->memberWorkspace();
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()
            ->for($workspace)
            ->for($unverified)
            ->member()
            ->create();

        $this->postJson($this->initialiseUrl($workspace), $this->metadata())
            ->assertUnauthorized();

        $this->actingAs($unverified)
            ->postJson($this->initialiseUrl($workspace), $this->metadata())
            ->assertForbidden();
    }

    public function test_initialisation_rejects_an_inaccessible_workspace_without_disclosure(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withOwner()->create();

        $this->actingAs($user)
            ->postJson($this->initialiseUrl($workspace), $this->metadata())
            ->assertNotFound();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_initialisation_rejects_unsupported_or_inconsistent_metadata(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $url = $this->initialiseUrl($workspace);

        $this->actingAs($user)
            ->postJson($url, $this->metadata([
                'filename' => 'budget.csv',
                'media_type' => 'text/csv',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filename', 'media_type']);

        $this->actingAs($user)
            ->postJson($url, $this->metadata([
                'filename' => 'report.pdf',
                'media_type' => 'text/plain',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media_type']);

        $this->actingAs($user)
            ->postJson($url, $this->metadata(['size_bytes' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size_bytes']);

        $this->actingAs($user)
            ->postJson($url, $this->metadata([
                'size_bytes' => (25 * 1024 * 1024) + 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size_bytes']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_supported_formats_can_be_initialised(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $formats = [
            ['file.pdf', 'application/pdf'],
            ['file.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['file.doc', 'application/msword'],
            ['file.rtf', 'application/rtf'],
            ['file.txt', 'text/plain'],
            ['file.md', 'text/markdown'],
        ];
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('createUploadRequest')
            ->times(count($formats))
            ->andReturn($this->signedUpload());

        foreach ($formats as [$filename, $mediaType]) {
            $this->actingAs($user)
                ->postJson($this->initialiseUrl($workspace), $this->metadata([
                    'filename' => $filename,
                    'media_type' => $mediaType,
                ]))
                ->assertCreated();
        }
    }

    public function test_completion_verifies_object_and_transitions_only_to_uploaded(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $document = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy')
            ->create(['size_bytes' => 42_000]);
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('objectSize')
            ->once()
            ->withArgs(fn (Document $candidate): bool => $candidate->is($document))
            ->andReturn(42_000);
        Queue::fake();

        $this->actingAs($user)
            ->postJson($this->completionUrl($workspace, $document))
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                DocumentStatus::Uploaded->value,
            )
            ->assertJsonMissingPath('data.storage_key');

        $this->assertSame(
            DocumentStatus::Uploaded,
            $document->refresh()->status,
        );
        Queue::assertNothingPushed();
    }

    public function test_completion_rejects_missing_object_without_advancing_state(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $document = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy')
            ->create();
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('objectSize')->once()->andReturn(null);

        $this->actingAs($user)
            ->postJson($this->completionUrl($workspace, $document))
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'The uploaded object could not be verified.',
            );

        $this->assertSame(
            DocumentStatus::Uploading,
            $document->refresh()->status,
        );
    }

    public function test_completion_rejects_size_mismatch_without_advancing_state(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $document = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy')
            ->create(['size_bytes' => 42_000]);
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldReceive('objectSize')->once()->andReturn(41_999);

        $this->actingAs($user)
            ->postJson($this->completionUrl($workspace, $document))
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'The uploaded object size does not match the authorised upload.',
            );

        $this->assertSame(
            DocumentStatus::Uploading,
            $document->refresh()->status,
        );
    }

    public function test_completion_rejects_invalid_lifecycle_transition(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $document = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy')
            ->indexed()
            ->create();
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldNotReceive('objectSize');

        $this->actingAs($user)
            ->postJson($this->completionUrl($workspace, $document))
            ->assertConflict();

        $this->assertSame(
            DocumentStatus::Indexed,
            $document->refresh()->status,
        );
    }

    public function test_repeated_completion_is_idempotent(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $document = Document::factory()
            ->for($workspace)
            ->for($user, 'createdBy')
            ->uploaded()
            ->create();
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldNotReceive('objectSize');

        $this->actingAs($user)
            ->postJson($this->completionUrl($workspace, $document))
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                DocumentStatus::Uploaded->value,
            );

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_cross_workspace_document_completion_fails_closed(): void
    {
        [$user, $workspace] = $this->memberWorkspace();
        $otherWorkspace = Workspace::factory()->withOwner()->create();
        $document = Document::factory()->for($otherWorkspace)->create();
        $storage = $this->mock(DocumentObjectStorage::class);
        $storage->shouldNotReceive('objectSize');

        $this->actingAs($user)
            ->postJson($this->completionUrl($workspace, $document))
            ->assertNotFound();
    }

    /**
     * @return array{User, Workspace}
     */
    private function memberWorkspace(
        WorkspaceRole $role = WorkspaceRole::Member,
    ): array {
        $user = User::factory()->create();

        if ($role === WorkspaceRole::Owner) {
            $workspace = Workspace::factory()->withOwner($user)->create();

            return [$user, $workspace];
        }

        $workspace = Workspace::factory()->withOwner()->create();
        WorkspaceMembership::factory()
            ->for($workspace)
            ->for($user)
            ->state(['role' => $role])
            ->create();

        return [$user, $workspace];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function metadata(array $overrides = []): array
    {
        return array_merge([
            'filename' => 'Quarterly Report.pdf',
            'media_type' => 'application/pdf',
            'size_bytes' => 42_000,
        ], $overrides);
    }

    /**
     * @return array{
     *     url: string,
     *     method: 'PUT',
     *     headers: array<string, string>,
     *     expires_at: string
     * }
     */
    private function signedUpload(): array
    {
        return [
            'url' => 'http://localhost:4566/test-bucket/signed-object?signature=test',
            'method' => 'PUT',
            'headers' => ['Content-Type' => 'application/pdf'],
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ];
    }

    private function configurationUrl(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/documents/uploads/configuration";
    }

    private function initialiseUrl(Workspace $workspace): string
    {
        return "/api/workspaces/{$workspace->public_id}/documents/uploads";
    }

    private function completionUrl(
        Workspace $workspace,
        Document $document,
    ): string {
        return "/api/workspaces/{$workspace->public_id}"
            ."/documents/{$document->public_id}/uploads/complete";
    }
}
