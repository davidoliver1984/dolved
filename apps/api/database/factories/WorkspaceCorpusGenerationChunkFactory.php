<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentChunk;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceCorpusGenerationChunk>
 */
class WorkspaceCorpusGenerationChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'workspace_corpus_generation_id' => fn (array $attributes): int => WorkspaceCorpusGeneration::factory()
                ->for(Workspace::query()->findOrFail($attributes['workspace_id']))
                ->create()
                ->id,
            'document_chunk_id' => fn (array $attributes): int => DocumentChunk::factory()
                ->for(Workspace::query()->findOrFail($attributes['workspace_id']))
                ->create()
                ->id,
        ];
    }
}
