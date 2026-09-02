<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\BulkOperation;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceNotification;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Workspace;

final class ResolveDocumentGovernanceNotificationRoute
{
    public function handle(DocumentGovernanceNotification $notification, Workspace $workspace): ?string
    {
        if ($notification->target_public_id === null) {
            return null;
        }

        $base = "/app/workspaces/{$workspace->public_id}";

        return match ($notification->target_kind) {
            'document', 'document_version' => Document::query()
                ->whereBelongsTo($workspace)->where('public_id', $notification->target_public_id)->exists()
                    ? "{$base}/documents/{$notification->target_public_id}" : null,
            'family', 'document_family' => DocumentFamily::query()
                ->whereBelongsTo($workspace)->where('public_id', $notification->target_public_id)
                ->whereNull('tombstoned_at')->exists()
                    ? "{$base}/documents/families/{$notification->target_public_id}" : null,
            'bulk_operation' => BulkOperation::query()
                ->whereBelongsTo($workspace)->where('public_id', $notification->target_public_id)->exists()
                    ? "{$base}/documents/bulk/{$notification->target_public_id}" : null,
            'import_item' => $this->importRoute($workspace, $notification->target_public_id, $base),
            'import_batch' => ImportBatch::query()
                ->whereBelongsTo($workspace)->where('public_id', $notification->target_public_id)->exists()
                    ? "{$base}/documents/imports?batch={$notification->target_public_id}" : null,
            default => null,
        };
    }

    private function importRoute(Workspace $workspace, string $itemPublicId, string $base): ?string
    {
        $item = ImportItem::query()
            ->with('batch:id,public_id')
            ->whereBelongsTo($workspace)
            ->where('public_id', $itemPublicId)
            ->first();

        return $item === null
            ? null
            : "{$base}/documents/imports?batch={$item->batch->public_id}&item={$item->public_id}";
    }
}
