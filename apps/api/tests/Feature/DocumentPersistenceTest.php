<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\CreateDocument;
use App\Enums\DocumentStatus;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\Documents\FindDocumentForWorkspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class DocumentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_creation_persists_identity_ownership_and_provenance(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();

        $document = $this->createDocument()->handle(
            $workspace,
            $creator,
            'Quarterly Report.pdf',
            'application/pdf',
            42_000,
        );

        $this->assertTrue(Str::isUuid($document->public_id));
        $this->assertSame(DocumentStatus::Uploading, $document->status);
        $this->assertSame('Quarterly Report.pdf', $document->source_filename);
        $this->assertSame('application/pdf', $document->media_type);
        $this->assertSame(42_000, $document->size_bytes);
        $this->assertSame(
            "workspaces/{$workspace->public_id}/documents/{$document->public_id}/source",
            $document->storage_key,
        );
        $this->assertTrue($document->workspace->is($workspace));
        $this->assertTrue($document->createdBy->is($creator));
    }

    public function test_user_filename_never_becomes_part_of_the_storage_key(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();

        $document = $this->createDocument()->handle(
            $workspace,
            $creator,
            '../../private/report.pdf',
            'application/pdf',
            1_024,
        );

        $this->assertSame('../../private/report.pdf', $document->source_filename);
        $this->assertStringNotContainsString('private', $document->storage_key);
        $this->assertStringNotContainsString('report.pdf', $document->storage_key);
        $this->assertStringStartsWith(
            "workspaces/{$workspace->public_id}/documents/",
            $document->storage_key,
        );
    }

    public function test_workspace_and_provenance_relationships_are_available(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();
        $document = Document::factory()
            ->for($workspace)
            ->for($creator, 'createdBy')
            ->create();

        $this->assertTrue($workspace->fresh()->documents->contains($document));
        $this->assertTrue($creator->fresh()->createdDocuments->contains($document));
        $this->assertTrue($document->workspace->is($workspace));
        $this->assertTrue($document->createdBy->is($creator));
    }

    public function test_factory_supports_every_lifecycle_state_and_enum_casting(): void
    {
        $documents = [
            Document::factory()->uploading()->create(),
            Document::factory()->uploaded()->create(),
            Document::factory()->queued()->create(),
            Document::factory()->processing()->create(),
            Document::factory()->indexed()->create(),
            Document::factory()->failed()->create(),
            Document::factory()->deleting()->create(),
            Document::factory()->deleted()->create(),
        ];

        $this->assertSame(
            DocumentStatus::cases(),
            array_map(
                fn (Document $document): DocumentStatus => $document->status,
                $documents,
            ),
        );
        $this->assertSame('extraction_failed', $documents[5]->failure_category);
        $this->assertNotEmpty($documents[5]->failure_message);
    }

    public function test_database_defaults_lifecycle_to_uploading(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();
        $publicId = (string) Str::uuid();

        $documentId = DB::table('documents')->insertGetId([
            'public_id' => $publicId,
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $creator->id,
            'source_filename' => 'default.txt',
            'media_type' => 'text/plain',
            'size_bytes' => 7,
            'storage_key' => "workspaces/{$workspace->public_id}/documents/{$publicId}/source",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            DocumentStatus::Uploading,
            Document::query()->findOrFail($documentId)->status,
        );
    }

    public function test_database_rejects_an_unknown_lifecycle_state(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('documents')->insert([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $creator->id,
            'status' => 'ready',
            'source_filename' => 'invalid.txt',
            'media_type' => 'text/plain',
            'size_bytes' => 7,
            'storage_key' => 'workspaces/example/documents/invalid/source',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_failed_document_requires_diagnostic_information(): void
    {
        $this->expectException(LogicException::class);

        Document::factory()->create([
            'status' => DocumentStatus::Failed,
            'failure_category' => null,
            'failure_message' => null,
        ]);
    }

    public function test_cross_workspace_lookup_fails_closed(): void
    {
        $owningWorkspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $document = Document::factory()->for($owningWorkspace)->create();

        $this->expectException(ModelNotFoundException::class);

        $this->findDocument()->handle($otherWorkspace, $document->public_id);
    }

    public function test_workspace_scoped_lookup_returns_the_owned_document(): void
    {
        $workspace = Workspace::factory()->create();
        $document = Document::factory()->for($workspace)->create();

        $resolved = $this->findDocument()->handle(
            $workspace,
            $document->public_id,
        );

        $this->assertTrue($resolved->is($document));
    }

    public function test_resource_does_not_expose_storage_or_failure_details(): void
    {
        $document = Document::factory()->failed()->create();
        $resource = new DocumentResource($document);
        $data = $resource->toArray(Request::create('/'));

        $this->assertSame($document->public_id, $data['public_id']);
        $this->assertSame(DocumentStatus::Failed->value, $data['status']);
        $this->assertArrayNotHasKey('storage_key', $data);
        $this->assertArrayNotHasKey('failure_category', $data);
        $this->assertArrayNotHasKey('failure_message', $data);
        $this->assertArrayNotHasKey('workspace_id', $data);
        $this->assertArrayNotHasKey('created_by_user_id', $data);
    }

    public function test_reuploading_the_same_filename_creates_independent_documents(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();

        $first = $this->createDocument()->handle(
            $workspace,
            $creator,
            'handbook.pdf',
            'application/pdf',
            1_000,
        );
        $second = $this->createDocument()->handle(
            $workspace,
            $creator,
            'handbook.pdf',
            'application/pdf',
            1_000,
        );

        $this->assertNotSame($first->public_id, $second->public_id);
        $this->assertNotSame($first->storage_key, $second->storage_key);
        $this->assertDatabaseCount('documents', 2);
    }

    public function test_document_public_id_is_unique(): void
    {
        $document = Document::factory()->create();

        $this->expectException(QueryException::class);

        Document::factory()->create([
            'public_id' => $document->public_id,
        ]);
    }

    public function test_document_public_id_is_immutable(): void
    {
        $document = Document::factory()->create();
        $document->public_id = (string) Str::uuid();

        $this->expectException(LogicException::class);

        $document->save();
    }

    public function test_workspace_ownership_and_creation_provenance_are_immutable(): void
    {
        $document = Document::factory()->create();
        $originalWorkspace = $document->workspace;
        $originalCreator = $document->createdBy;

        try {
            $document->workspace()->associate(Workspace::factory()->create());
            $document->save();
            $this->fail('Document workspace ownership was changed.');
        } catch (LogicException) {
            $this->assertTrue($document->fresh()->workspace->is($originalWorkspace));
        }

        $document = $document->fresh();

        try {
            $document->createdBy()->associate(User::factory()->create());
            $document->save();
            $this->fail('Document creation provenance was changed.');
        } catch (LogicException) {
            $this->assertTrue($document->fresh()->createdBy->is($originalCreator));
        }
    }

    public function test_creation_rejects_invalid_intrinsic_metadata(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->createDocument()->handle(
            $workspace,
            $creator,
            'invalid.txt',
            'text/plain',
            -1,
        );
    }

    public function test_referenced_workspace_and_creator_cannot_be_deleted(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();
        Document::factory()
            ->for($workspace)
            ->for($creator, 'createdBy')
            ->create();

        try {
            $workspace->delete();
            $this->fail('A workspace with documents was deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
        }

        $this->expectException(QueryException::class);

        $creator->delete();
    }

    public function test_clean_migration_creates_document_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('documents', [
            'id',
            'public_id',
            'workspace_id',
            'created_by_user_id',
            'status',
            'source_filename',
            'media_type',
            'size_bytes',
            'storage_key',
            'failure_category',
            'failure_message',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('documents'));

        $this->assertTrue(
            $indexes->contains(
                fn (array $index): bool => $index['name'] === 'documents_public_id_unique',
            ),
        );
        $this->assertTrue(
            $indexes->contains(
                fn (array $index): bool => $index['name'] === 'documents_storage_key_unique',
            ),
        );
        $this->assertTrue(
            $indexes->contains(
                fn (array $index): bool => $index['name'] === 'documents_workspace_id_status_index',
            ),
        );
    }

    private function createDocument(): CreateDocument
    {
        return $this->app->make(CreateDocument::class);
    }

    private function findDocument(): FindDocumentForWorkspace
    {
        return $this->app->make(FindDocumentForWorkspace::class);
    }
}
