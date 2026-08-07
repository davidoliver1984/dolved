<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;

final readonly class AuthorisedKnowledgeScope
{
    public function __construct(
        public User $user,
        public Workspace $workspace,
        public ?WorkspaceCorpusGeneration $activeCorpusGeneration,
    ) {}
}
