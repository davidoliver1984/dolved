<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Retrieval\RollbackWorkspaceCorpusGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use Illuminate\Console\Command;

class RollbackCorpusGenerationCommand extends Command
{
    protected $signature = 'retrieval:rollback-corpus
        {workspace : Workspace public UUID}
        {generation : Superseded corpus-generation public UUID}
        {--reason= : Required business-audit reason}
        {--actor-email= : Optional platform user responsible for the rollback}';

    protected $description = 'Reverify and atomically roll back a workspace corpus generation';

    public function handle(RollbackWorkspaceCorpusGeneration $rollback): int
    {
        $workspace = Workspace::query()
            ->where('public_id', $this->argument('workspace'))
            ->firstOrFail();
        $target = WorkspaceCorpusGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $this->argument('generation'))
            ->firstOrFail();
        $actorEmail = $this->option('actor-email');
        $actor = is_string($actorEmail) && $actorEmail !== ''
            ? User::query()->where('email', mb_strtolower(trim($actorEmail)))->firstOrFail()
            : null;
        $reason = $this->option('reason');
        if (! is_string($reason) || trim($reason) === '') {
            $this->components->error('The --reason option is required.');

            return self::FAILURE;
        }
        $audit = $rollback->handle($workspace, $target, $actor, $reason);
        $this->components->info("Rollback {$audit->public_id} completed and audited.");

        return self::SUCCESS;
    }
}
