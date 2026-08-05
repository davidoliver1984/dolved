<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class VectorPersistenceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_embedding_profile_persists_immutable_semantic_lineage(): void
    {
        $profile = EmbeddingProfile::factory()->voyageV1()->create();

        $this->assertTrue(Str::isUuid($profile->public_id));
        $this->assertSame(
            'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            $profile->fingerprint,
        );
        $this->assertSame('voyage', $profile->provider);
        $this->assertSame('voyage-4-large', $profile->model);
        $this->assertSame(1024, $profile->dimensions);
        $this->assertFalse($profile->truncation);

        $profile->model = 'changed-model';

        $this->expectException(LogicException::class);
        $profile->save();
    }

    public function test_embedding_profile_fingerprint_is_unique(): void
    {
        $profile = EmbeddingProfile::factory()->create();

        $this->expectException(QueryException::class);
        EmbeddingProfile::factory()->create([
            'fingerprint' => $profile->fingerprint,
        ]);
    }

    public function test_embedding_space_factories_cast_every_lifecycle_state(): void
    {
        $generations = [
            EmbeddingSpaceGeneration::factory()->create(),
            EmbeddingSpaceGeneration::factory()->verifying()->create(),
            EmbeddingSpaceGeneration::factory()->available()->create(),
            EmbeddingSpaceGeneration::factory()->retiring()->create(),
            EmbeddingSpaceGeneration::factory()->retired()->create(),
        ];

        $this->assertSame(
            EmbeddingSpaceGenerationStatus::cases(),
            array_map(
                fn (EmbeddingSpaceGeneration $generation): EmbeddingSpaceGenerationStatus => $generation->status,
                $generations,
            ),
        );
        $this->assertNull($generations[0]->available_at);
        $this->assertInstanceOf(CarbonInterface::class, $generations[2]->available_at);
        $this->assertInstanceOf(CarbonInterface::class, $generations[4]->retired_at);
    }

    public function test_embedding_space_retains_profile_relationship_and_compatibility(): void
    {
        $profile = EmbeddingProfile::factory()->create(['dimensions' => 3]);
        $generation = EmbeddingSpaceGeneration::factory()
            ->for($profile)
            ->available()
            ->create(['dimensions' => 3]);

        $this->assertTrue($generation->embeddingProfile->is($profile));
        $this->assertTrue(
            $profile->fresh()->embeddingSpaceGenerations->contains($generation)
        );

        $this->expectException(LogicException::class);
        EmbeddingSpaceGeneration::factory()
            ->for($profile)
            ->create(['dimensions' => 4]);
    }

    public function test_workspace_corpus_factories_cast_every_lifecycle_state(): void
    {
        $generations = [
            WorkspaceCorpusGeneration::factory()->create(),
            WorkspaceCorpusGeneration::factory()->verifying()->create(),
            WorkspaceCorpusGeneration::factory()->active()->create(),
            WorkspaceCorpusGeneration::factory()->superseded()->create(),
            WorkspaceCorpusGeneration::factory()->retired()->create(),
        ];

        $this->assertSame(
            WorkspaceCorpusGenerationStatus::cases(),
            array_map(
                fn (WorkspaceCorpusGeneration $generation): WorkspaceCorpusGenerationStatus => $generation->status,
                $generations,
            ),
        );
        $this->assertInstanceOf(CarbonInterface::class, $generations[2]->activated_at);
        $this->assertInstanceOf(CarbonInterface::class, $generations[3]->superseded_at);
        $this->assertInstanceOf(CarbonInterface::class, $generations[4]->retired_at);
    }

    public function test_corpus_generation_belongs_to_one_workspace_and_embedding_space(): void
    {
        $workspace = Workspace::factory()->create();
        $embeddingSpace = EmbeddingSpaceGeneration::factory()->available()->create();
        $corpus = WorkspaceCorpusGeneration::factory()
            ->for($workspace)
            ->for($embeddingSpace)
            ->create();

        $this->assertTrue($corpus->workspace->is($workspace));
        $this->assertTrue($corpus->embeddingSpaceGeneration->is($embeddingSpace));
        $this->assertTrue(
            $workspace->fresh()->workspaceCorpusGenerations->contains($corpus)
        );
        $this->assertTrue(
            $embeddingSpace->fresh()->workspaceCorpusGenerations->contains($corpus)
        );
    }

    public function test_database_allows_at_most_one_active_corpus_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        WorkspaceCorpusGeneration::factory()->for($workspace)->active()->create();

        $this->expectException(QueryException::class);
        WorkspaceCorpusGeneration::factory()->for($workspace)->active()->create();
    }

    public function test_active_corpus_requires_an_available_embedding_space(): void
    {
        $embeddingSpace = EmbeddingSpaceGeneration::factory()->create();

        $this->expectException(LogicException::class);
        WorkspaceCorpusGeneration::factory()
            ->for($embeddingSpace)
            ->active()
            ->create();
    }

    public function test_embedding_space_with_active_corpus_cannot_be_retired(): void
    {
        $embeddingSpace = EmbeddingSpaceGeneration::factory()->available()->create();
        WorkspaceCorpusGeneration::factory()
            ->for($embeddingSpace)
            ->active()
            ->create();

        $embeddingSpace->status = EmbeddingSpaceGenerationStatus::Retiring;

        $this->expectException(LogicException::class);
        $embeddingSpace->save();
    }

    public function test_workspace_exposes_its_single_active_corpus_relationship(): void
    {
        $workspace = Workspace::factory()->create();
        WorkspaceCorpusGeneration::factory()->for($workspace)->create();
        $active = WorkspaceCorpusGeneration::factory()
            ->for($workspace)
            ->active()
            ->create();
        $workspace->activeCorpusGeneration()->associate($active);
        $workspace->save();

        $this->assertTrue($workspace->fresh()->activeCorpusGeneration->is($active));
    }

    public function test_active_corpus_pointer_cannot_cross_workspace_boundary(): void
    {
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $otherActive = WorkspaceCorpusGeneration::factory()
            ->for($otherWorkspace)
            ->active()
            ->create();

        $this->expectException(QueryException::class);
        DB::table('workspaces')->where('id', $workspace->id)->update([
            'active_workspace_corpus_generation_id' => $otherActive->id,
        ]);
    }

    public function test_canonical_chunk_persists_identity_text_ordinal_and_provenance(): void
    {
        $workspace = Workspace::factory()->create();
        $document = Document::factory()->for($workspace)->create();
        $chunk = DocumentChunk::factory()
            ->for($workspace)
            ->for($document)
            ->create([
                'ordinal' => 0,
                'text' => 'Canonical accepted chunk text.',
                'token_count' => 5,
            ]);

        $this->assertTrue(Str::isUuid($chunk->public_id));
        $this->assertSame(0, $chunk->ordinal);
        $this->assertSame('Canonical accepted chunk text.', $chunk->text);
        $this->assertSame(5, $chunk->token_count);
        $this->assertIsArray($chunk->configuration);
        $this->assertIsArray($chunk->provenance);
        $this->assertNotEmpty($chunk->provenance);
        $this->assertTrue($chunk->workspace->is($workspace));
        $this->assertTrue($chunk->document->is($document));
        $this->assertTrue($workspace->fresh()->documentChunks->contains($chunk));
        $this->assertTrue($document->fresh()->chunks->contains($chunk));
    }

    public function test_canonical_chunk_is_immutable(): void
    {
        $chunk = DocumentChunk::factory()->create();
        $chunk->text = 'Changed text';

        $this->expectException(LogicException::class);
        $chunk->save();
    }

    public function test_chunk_workspace_must_match_its_document_workspace(): void
    {
        $owningWorkspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $document = Document::factory()->for($owningWorkspace)->create();

        $this->expectException(QueryException::class);
        DocumentChunk::factory()
            ->for($otherWorkspace)
            ->for($document)
            ->create();
    }

    public function test_chunk_ordinal_is_unique_within_document_configuration(): void
    {
        $workspace = Workspace::factory()->create();
        $document = Document::factory()->for($workspace)->create();
        $first = DocumentChunk::factory()
            ->for($workspace)
            ->for($document)
            ->create(['ordinal' => 0]);

        $this->expectException(QueryException::class);
        DocumentChunk::factory()
            ->for($workspace)
            ->for($document)
            ->create([
                'ordinal' => 0,
                'configuration_fingerprint' => $first->configuration_fingerprint,
            ]);
    }

    public function test_chunk_can_be_assigned_to_multiple_compatible_corpus_generations(): void
    {
        $workspace = Workspace::factory()->create();
        $document = Document::factory()->for($workspace)->create();
        $chunk = DocumentChunk::factory()
            ->for($workspace)
            ->for($document)
            ->create();
        $firstCorpus = WorkspaceCorpusGeneration::factory()->for($workspace)->create();
        $secondCorpus = WorkspaceCorpusGeneration::factory()->for($workspace)->create();

        $first = WorkspaceCorpusGenerationChunk::factory()->create([
            'workspace_id' => $workspace->id,
            'workspace_corpus_generation_id' => $firstCorpus->id,
            'document_chunk_id' => $chunk->id,
        ]);
        $second = WorkspaceCorpusGenerationChunk::factory()->create([
            'workspace_id' => $workspace->id,
            'workspace_corpus_generation_id' => $secondCorpus->id,
            'document_chunk_id' => $chunk->id,
        ]);

        $this->assertTrue($first->workspaceCorpusGeneration->is($firstCorpus));
        $this->assertTrue($first->documentChunk->is($chunk));
        $this->assertTrue($second->workspaceCorpusGeneration->is($secondCorpus));
        $this->assertCount(2, $chunk->fresh()->workspaceCorpusGenerations);
        $this->assertTrue($firstCorpus->fresh()->documentChunks->contains($chunk));
    }

    public function test_corpus_chunk_assignment_cannot_cross_workspace_boundary(): void
    {
        $firstWorkspace = Workspace::factory()->create();
        $secondWorkspace = Workspace::factory()->create();
        $corpus = WorkspaceCorpusGeneration::factory()->for($firstWorkspace)->create();
        $chunk = DocumentChunk::factory()->for($secondWorkspace)->create();

        $this->expectException(QueryException::class);
        DB::table('workspace_corpus_generation_chunks')->insert([
            'workspace_id' => $firstWorkspace->id,
            'workspace_corpus_generation_id' => $corpus->id,
            'document_chunk_id' => $chunk->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_unknown_generation_states(): void
    {
        $profile = EmbeddingProfile::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('embedding_space_generations')->insert([
            'public_id' => (string) Str::uuid(),
            'embedding_profile_id' => $profile->id,
            'collection_name' => 'invalid-state',
            'vector_name' => 'dense',
            'dimensions' => $profile->dimensions,
            'distance' => 'cosine',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_schema_contains_authoritative_foundation_without_raw_vectors(): void
    {
        $this->assertTrue(Schema::hasColumns('embedding_profiles', [
            'id',
            'public_id',
            'fingerprint',
            'provider',
            'model',
            'dimensions',
            'output_dtype',
            'document_input_type',
            'query_input_type',
            'normalisation',
            'truncation',
            'model_revision',
            'adapter_version',
        ]));
        $this->assertTrue(Schema::hasColumns('embedding_space_generations', [
            'id',
            'public_id',
            'embedding_profile_id',
            'collection_name',
            'vector_name',
            'dimensions',
            'distance',
            'status',
            'available_at',
            'retired_at',
        ]));
        $this->assertTrue(Schema::hasColumns('workspace_corpus_generations', [
            'id',
            'public_id',
            'workspace_id',
            'embedding_space_generation_id',
            'status',
            'activated_at',
            'superseded_at',
            'retired_at',
        ]));
        $this->assertTrue(Schema::hasColumn(
            'workspaces',
            'active_workspace_corpus_generation_id'
        ));
        $this->assertTrue(Schema::hasColumns('document_chunks', [
            'id',
            'public_id',
            'workspace_id',
            'document_id',
            'ordinal',
            'text',
            'token_count',
            'strategy_name',
            'strategy_version',
            'configuration',
            'configuration_fingerprint',
            'provenance',
        ]));
        $this->assertTrue(Schema::hasColumns('workspace_corpus_generation_chunks', [
            'id',
            'workspace_id',
            'workspace_corpus_generation_id',
            'document_chunk_id',
        ]));
        $this->assertFalse(Schema::hasColumn('document_chunks', 'vector'));
        $this->assertFalse(Schema::hasColumn('document_chunks', 'embedding'));
        $this->assertFalse(Schema::hasColumn('document_chunks', 'values'));

        $corpusIndexes = collect(Schema::getIndexes('workspace_corpus_generations'))
            ->pluck('name');
        $this->assertTrue(
            $corpusIndexes->contains(
                'workspace_corpus_generations_one_active_per_workspace'
            )
        );
    }
}
