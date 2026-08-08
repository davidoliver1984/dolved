<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Retrieval\RebuildHybridCorpusGeneration;
use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\SparseSpaceGeneration;
use App\Models\Workspace;
use Illuminate\Console\Command;

class RebuildHybridCorpusCommand extends Command
{
    protected $signature = 'retrieval:rebuild-hybrid-corpus
        {workspace : Workspace public UUID}
        {sparse-space : Sparse-space generation public UUID}
        {--batch-size=50 : Canonical chunks sent per bounded rebuild request}';

    protected $description = 'Build, verify and atomically activate a hybrid workspace corpus generation';

    public function handle(RebuildHybridCorpusGeneration $rebuild): int
    {
        $workspace = Workspace::query()
            ->where('public_id', $this->argument('workspace'))
            ->firstOrFail();
        $workspace->loadMissing('activeCorpusGeneration');
        $sparse = SparseSpaceGeneration::query()
            ->where('public_id', $this->argument('sparse-space'))
            ->where('embedding_space_generation_id', $workspace->activeCorpusGeneration?->embedding_space_generation_id)
            ->where('status', EmbeddingSpaceGenerationStatus::Available->value)
            ->firstOrFail();
        $generation = $rebuild->handle(
            $workspace,
            $sparse,
            (int) $this->option('batch-size'),
        );
        $this->components->info("Hybrid corpus generation {$generation->public_id} is active.");

        return self::SUCCESS;
    }
}
