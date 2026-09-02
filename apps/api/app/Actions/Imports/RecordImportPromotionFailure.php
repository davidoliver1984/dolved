<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\DocumentGovernanceEventKey;
use App\Enums\PromotionAttemptStatus;
use App\Exceptions\ImportPromotionException;
use App\Models\PromotionAttempt;
use App\Models\PromotionAttemptFailure;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use Illuminate\Support\Facades\DB;

final readonly class RecordImportPromotionFailure
{
    public function __construct(private RecordDocumentGovernanceEvent $events) {}

    /** @param array<string, scalar|null> $safeContext */
    public function handle(PromotionAttempt $attempt, string $leaseToken, int $leaseGeneration, string $failureCode, array $safeContext = []): PromotionAttempt
    {
        return DB::transaction(function () use ($attempt, $leaseToken, $leaseGeneration, $failureCode, $safeContext): PromotionAttempt {
            $locked = PromotionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! in_array($locked->status, [PromotionAttemptStatus::Copying, PromotionAttemptStatus::SourceVerified], true)
                || $locked->lease_generation !== $leaseGeneration
                || $locked->lease_token_hash === null
                || ! hash_equals($locked->lease_token_hash, hash('sha256', $leaseToken))) {
                throw ImportPromotionException::conflict('stale_promotion_lease');
            }
            PromotionAttemptFailure::query()->firstOrCreate(
                ['promotion_attempt_id' => $locked->id, 'lease_generation' => $leaseGeneration],
                ['failure_code' => $failureCode, 'safe_context' => $safeContext],
            );
            $recordedFailureCount = $locked->failures()->count();
            $locked->refresh();
            $locked->lease_token_hash = null;
            $locked->lease_expires_at = now();
            if ($recordedFailureCount >= (int) config('imports.promotion.failure_ceiling')) {
                $locked->status = PromotionAttemptStatus::Failed;
                $locked->terminal_reason = 'technical_exhaustion';
            }
            $locked->save();
            if ($locked->status === PromotionAttemptStatus::Failed) {
                $locked->loadMissing('actor');
                $this->events->record(
                    $locked->workspace()->firstOrFail(),
                    DocumentGovernanceEventKey::PromotionFailed,
                    $locked->public_id,
                    $locked->public_id,
                    [
                        'initiating_user_public_id' => $locked->actor?->public_id,
                        'target_kind' => 'import_item',
                        'target_public_id' => $locked->item()->value('public_id'),
                        'target_display_label' => 'Import promotion',
                    ],
                );
            }

            return $locked;
        });
    }
}
