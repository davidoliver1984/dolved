<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Jobs\SyncDifyDocument;
use App\Models\DifyWorkspaceBinding;
use App\Models\Document;

final class ReconcileDifyDocuments
{
    public function handle(): int
    {
        $workspaceIds = DifyWorkspaceBinding::query()->where('enabled', true)->pluck('workspace_id');
        if ($workspaceIds->isEmpty()) {
            return 0;
        }
        $count = 0;
        Document::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->where(function ($query): void {
                $query->where(function ($eligible): void {
                    $eligible->where('status', 'indexed')->where('governance_status', 'approved');
                })->orWhereHas('difyBindings');
            })
            ->orderBy('id')
            ->chunkById(100, function ($documents) use (&$count): void {
                foreach ($documents as $document) {
                    SyncDifyDocument::dispatch($document->id);
                    $count++;
                }
            });

        return $count;
    }
}
