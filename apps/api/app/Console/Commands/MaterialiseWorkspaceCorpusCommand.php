<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Retrieval\MaterialiseWorkspaceCorpusGeneration;
use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseSpaceGeneration;
use App\Models\Workspace;
use Illuminate\Console\Command;

class MaterialiseWorkspaceCorpusCommand extends Command
{
    protected $signature = 'retrieval:materialise-workspace-corpus
        {workspace : Exactly one authorised workspace public UUID}
        {embedding-space : Target dense-space generation public UUID}
        {sparse-space : Compatible target sparse-space generation public UUID}
        {--batch-size=100 : Canonical chunks sent per deterministic request}';

    protected $description = 'Materialise, verify and atomically activate one workspace corpus in new vector spaces';

    public function handle(MaterialiseWorkspaceCorpusGeneration $materialise): int
    {
        $workspace = Workspace::query()->where('public_id', $this->argument('workspace'))->firstOrFail();
        $embedding = EmbeddingSpaceGeneration::query()
            ->where('public_id', $this->argument('embedding-space'))
            ->where('status', EmbeddingSpaceGenerationStatus::Available->value)
            ->firstOrFail();
        $sparse = SparseSpaceGeneration::query()
            ->where('public_id', $this->argument('sparse-space'))
            ->where('embedding_space_generation_id', $embedding->id)
            ->where('status', EmbeddingSpaceGenerationStatus::Available->value)
            ->firstOrFail();
        $generation = $materialise->handle($workspace, $embedding, $sparse, (int) $this->option('batch-size'));
        $this->components->info("Workspace corpus generation {$generation->public_id} is active with {$generation->expected_point_count} points.");

        return self::SUCCESS;
    }
}
