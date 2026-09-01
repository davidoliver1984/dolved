<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkItemStatus;
use App\Enums\BulkSubordinateKind;
use App\Enums\DocumentContentCloneStatus;
use App\Enums\IngestionAttemptStatus;
use App\Enums\PromotionAttemptStatus;
use App\Models\BulkOperation;
use App\Models\BulkOperationItem;
use App\Models\BulkOperationItemSubordinateTransition;
use App\Models\DocumentContentCloneOperation;
use App\Models\IngestionEventClaim;
use App\Models\OutboxEvent;
use App\Models\PromotionAttempt;
use App\Support\BulkOperations\RecordBulkOperationAudit;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileBulkOperationSubordinates
{
    public function __construct(
        private RecordBulkOperationAudit $audit,
        private FinalizeBulkOperationAttempt $finalize,
    ) {}

    public function handle(BulkOperation $operation, int $limit = 50): int
    {
        $items = BulkOperationItem::query()->where('bulk_operation_id', $operation->id)
            ->where('execution_status', BulkItemStatus::WaitingOnSubordinate->value)
            ->orderBy('id')->limit($limit)->get();
        $resolved = 0;
        foreach ($items as $item) {
            $outcome = $this->outcome($item);
            if ($outcome === null) {
                continue;
            }
            DB::transaction(function () use ($item, $outcome): void {
                $operationIdentity = BulkOperation::query()->findOrFail($item->bulk_operation_id);
                $operation = BulkOperation::query()->lockForUpdate()->findOrFail($operationIdentity->id);
                $locked = BulkOperationItem::query()->lockForUpdate()->findOrFail($item->id);
                if ($locked->execution_status !== BulkItemStatus::WaitingOnSubordinate) {
                    return;
                }
                $event = $this->audit->record($operation, 'bulk_operation.subordinate_reconciled', 'laravel.bulk-reconciler', [
                    'item_status' => $outcome['status']->value,
                    'operation_type' => $operation->operation_type->value,
                    'subordinate_kind' => $locked->subordinate_kind->value,
                    'terminal_reason' => $outcome['reason'],
                ], $locked);
                $locked->forceFill([
                    'execution_status' => $outcome['status'],
                    'terminal_reason' => $outcome['reason'],
                    'result_identity' => $outcome['status'] === BulkItemStatus::Succeeded
                        ? $outcome['result_identity'] : null,
                    'completed_at' => now(),
                    'audit_event_id' => $event->id,
                ])->save();
                $this->finalize->convergeLockedParent($operation);
            }, 3);
            $resolved++;
        }

        return $resolved;
    }

    /** @return null|array{status: BulkItemStatus, reason: ?string, result_identity: ?string} */
    private function outcome(BulkOperationItem $item): ?array
    {
        if ($item->subordinate_kind === BulkSubordinateKind::PromotionAttempt) {
            $attempt = PromotionAttempt::query()->where('public_id', $item->subordinate_identity_value)->first();
            if ($attempt === null) {
                return ['status' => BulkItemStatus::FailedPermanent, 'reason' => 'promotion_technical_failure', 'result_identity' => null];
            }

            return match ($attempt->status) {
                PromotionAttemptStatus::Committed => ['status' => BulkItemStatus::Succeeded, 'reason' => null, 'result_identity' => $attempt->committedDocument()->value('public_id')],
                PromotionAttemptStatus::Conflict => ['status' => BulkItemStatus::FailedPermanent, 'reason' => 'promotion_conflict', 'result_identity' => null],
                PromotionAttemptStatus::Failed => ['status' => BulkItemStatus::FailedPermanent, 'reason' => 'promotion_technical_failure', 'result_identity' => null],
                PromotionAttemptStatus::Abandoned => ['status' => BulkItemStatus::FailedPermanent, 'reason' => 'promotion_abandoned_externally', 'result_identity' => null],
                PromotionAttemptStatus::Expired => ['status' => BulkItemStatus::FailedPermanent, 'reason' => 'promotion_expired', 'result_identity' => null],
                default => null,
            };
        }
        if ($item->subordinate_kind === BulkSubordinateKind::ContentCloneOperation) {
            $clone = DocumentContentCloneOperation::query()->where('public_id', $item->subordinate_identity_value)->first();
            if ($clone?->status === DocumentContentCloneStatus::Indexed) {
                return ['status' => BulkItemStatus::Succeeded, 'reason' => null, 'result_identity' => $clone->targetDocument()->value('public_id')];
            }
            if ($clone?->status === DocumentContentCloneStatus::FallbackReady) {
                $this->advanceToFallback($item, $clone);
            }
        }
        if ($item->subordinate_kind === BulkSubordinateKind::FullIngestionFallback) {
            $ingestion = IngestionEventClaim::query()->where('event_id', $item->subordinate_identity_value)->first();

            return match ($ingestion?->status) {
                IngestionAttemptStatus::Completed => [
                    'status' => BulkItemStatus::Succeeded,
                    'reason' => null,
                    'result_identity' => $ingestion->document()->value('public_id'),
                ],
                IngestionAttemptStatus::Failed, IngestionAttemptStatus::Cancelled => [
                    'status' => BulkItemStatus::FailedPermanent,
                    'reason' => 'full_ingestion_failed',
                    'result_identity' => null,
                ],
                default => null,
            };
        }

        return null;
    }

    private function advanceToFallback(BulkOperationItem $item, DocumentContentCloneOperation $clone): void
    {
        $correlation = $item->subordinateTransitions()->orderBy('ordinal')->value('correlation_identity');
        $event = OutboxEvent::query()
            ->where('document_public_id', $clone->targetDocument()->value('public_id'))
            ->where('correlation_id', $correlation)
            ->first();
        if (! $event instanceof OutboxEvent) {
            return;
        }

        DB::transaction(function () use ($item, $event): void {
            $locked = BulkOperationItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($locked->execution_status !== BulkItemStatus::WaitingOnSubordinate
                || $locked->subordinate_kind !== BulkSubordinateKind::ContentCloneOperation) {
                return;
            }
            $transitionKey = hash('sha256', implode(':', [
                (string) $locked->id,
                BulkSubordinateKind::FullIngestionFallback->value,
                $event->event_id,
                'fallback_started',
            ]));
            BulkOperationItemSubordinateTransition::query()->firstOrCreate(
                ['bulk_operation_item_id' => $locked->id, 'transition_key' => $transitionKey],
                [
                    'workspace_id' => $locked->workspace_id,
                    'ordinal' => ((int) $locked->subordinateTransitions()->max('ordinal')) + 1,
                    'subordinate_kind' => BulkSubordinateKind::FullIngestionFallback,
                    'subordinate_identity_kind' => 'event_id',
                    'subordinate_identity_value' => $event->event_id,
                    'transition_category' => 'fallback_started',
                    'recorded_at' => now(),
                    'correlation_identity' => $event->correlation_id,
                    'mapped_state_digest' => null,
                ],
            );
            $locked->forceFill([
                'subordinate_kind' => BulkSubordinateKind::FullIngestionFallback,
                'subordinate_identity_kind' => 'event_id',
                'subordinate_identity_value' => $event->event_id,
            ])->save();
        }, 3);
    }
}
