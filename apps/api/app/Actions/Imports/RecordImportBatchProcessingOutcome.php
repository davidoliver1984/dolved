<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\DocumentGovernanceEventKey;
use App\Enums\ImportPreflightStatus;
use App\Models\ImportBatch;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Illuminate\Support\Facades\DB;

final readonly class RecordImportBatchProcessingOutcome
{
    public function __construct(private RecordDocumentGovernanceEvent $events) {}

    public function handle(ImportBatch $batch): void
    {
        DB::transaction(function () use ($batch): void {
            $locked = ImportBatch::query()
                ->with(['initiatedBy', 'workspace'])
                ->lockForUpdate()
                ->findOrFail($batch->id);
            $items = $locked->items()
                ->whereNull('replaced_by_import_item_id')
                ->get(['id', 'preflight_status']);

            if ($items->isEmpty() || $items->contains(
                fn ($item): bool => $item->preflight_status === ImportPreflightStatus::Pending,
            )) {
                return;
            }

            $exceptionCount = $items->filter(
                fn ($item): bool => $item->preflight_status === ImportPreflightStatus::Rejected,
            )->count();
            $eventKey = $exceptionCount > 0
                ? DocumentGovernanceEventKey::ImportBatchCompletedWithExceptions
                : DocumentGovernanceEventKey::ImportBatchCompleted;

            $this->events->record(
                $locked->workspace,
                $eventKey,
                $locked->public_id,
                $locked->public_id,
                [
                    'initiating_user_public_id' => $locked->initiatedBy?->public_id,
                    'approval_required' => $items->contains(
                        fn ($item): bool => $item->preflight_status === ImportPreflightStatus::Verified,
                    ),
                    'item_count' => $items->count(),
                    'exception_count' => $exceptionCount,
                    'target_kind' => 'import_batch',
                    'target_public_id' => $locked->public_id,
                    'target_display_label' => $items->count().' document import',
                ],
            );
        });
    }
}
