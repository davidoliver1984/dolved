<?php

declare(strict_types=1);

namespace App\Queries\Retrieval;

use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Support\Retrieval\AuthorisedKnowledgeScope;

final readonly class BuildAuthorisedKnowledgeScope
{
    public function __construct(private FindWorkspaceForUser $workspaces) {}

    public function handle(User $user, string $workspacePublicId): AuthorisedKnowledgeScope
    {
        $workspace = $this->workspaces->handle($user, $workspacePublicId)->workspace;
        $workspace->load([
            'activeCorpusGeneration.embeddingSpaceGeneration.embeddingProfile',
        ]);

        return new AuthorisedKnowledgeScope(
            $user,
            $workspace,
            $workspace->activeCorpusGeneration,
        );
    }
}
