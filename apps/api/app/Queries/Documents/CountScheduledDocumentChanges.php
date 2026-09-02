<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Models\Document;
use App\Models\Workspace;

final class CountScheduledDocumentChanges
{
    public function handle(Workspace $workspace): int
    {
        return Document::query()
            ->whereBelongsTo($workspace)
            ->where('governance_status', DocumentGovernanceStatus::Approved->value)
            ->whereNotNull('approved_at')
            ->whereRaw('CASE WHEN effective_from > approved_at THEN effective_from ELSE approved_at END > ?', [now()])
            ->count();
    }
}
