<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Workspace;

final class CountAwaitingDocumentApproval
{
    public function handle(Workspace $workspace): int
    {
        return Document::query()
            ->whereBelongsTo($workspace)
            ->where('status', DocumentStatus::Indexed->value)
            ->where('governance_status', DocumentGovernanceStatus::Draft->value)
            ->count();
    }
}
