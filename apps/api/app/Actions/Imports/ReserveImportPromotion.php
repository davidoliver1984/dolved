<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\PromotionActorType;
use App\Enums\PromotionAttemptStatus;
use App\Enums\PromotionOperationKind;
use App\Exceptions\ImportPromotionException;
use App\Models\ImportItem;
use App\Models\PromotionAttempt;
use App\Models\User;
use App\Services\Documents\ImportPromotionObjectStorage;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ReserveImportPromotion
{
    public function __construct(
        private StructuredExtractionCanonicaliser $canonical,
        private ImportPromotionObjectStorage $storage,
    ) {}

    public function handle(ImportItem $item, User $actor, PromotionOperationKind $operation, string $idempotencyKey): PromotionAttempt
    {
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw ImportPromotionException::invalid('invalid_idempotency_key');
        }

        return DB::transaction(function () use ($item, $actor, $operation, $idempotencyKey): PromotionAttempt {
            $locked = ImportItem::query()->with(['batch', 'workspace', 'currentDecisionSnapshot'])->lockForUpdate()->findOrFail($item->id);
            $snapshot = $locked->currentDecisionSnapshot;
            if ($snapshot === null || $locked->batch->retention_expires_at->isPast()
                || ! $actor->workspaceMemberships()->where('workspace_id', $locked->workspace_id)->exists()) {
                throw ImportPromotionException::conflict('promotion_not_ready');
            }
            $payload = [
                'decision_snapshot_public_id' => $snapshot->public_id,
                'idempotency_key' => $idempotencyKey,
                'operation' => $operation->value,
            ];
            $digest = hash('sha256', $this->canonical->canonicalBytes($payload));
            $prior = PromotionAttempt::query()
                ->where('workspace_id', $locked->workspace_id)
                ->where('import_item_id', $locked->id)
                ->where('actor_type', PromotionActorType::Human->value)
                ->where('actor_user_id', $actor->id)
                ->where('operation_kind', $operation->value)
                ->where('client_idempotency_key', $idempotencyKey)
                ->first();
            if ($prior !== null) {
                if (! hash_equals($prior->request_digest_sha256, $digest)) {
                    throw ImportPromotionException::conflict('idempotency_conflict');
                }

                return $prior;
            }
            if (PromotionAttempt::query()->where('import_item_id', $locked->id)
                ->whereIn('status', [PromotionAttemptStatus::Reserved->value, PromotionAttemptStatus::Copying->value, PromotionAttemptStatus::SourceVerified->value])->exists()) {
                throw ImportPromotionException::conflict('promotion_already_open');
            }

            return PromotionAttempt::query()->create([
                'public_id' => (string) Str::uuid(),
                'import_item_id' => $locked->id,
                'workspace_id' => $locked->workspace_id,
                'decision_snapshot_id' => $snapshot->id,
                'attempt_ordinal' => ((int) PromotionAttempt::query()->where('import_item_id', $locked->id)->max('attempt_ordinal')) + 1,
                'status' => PromotionAttemptStatus::Reserved,
                'reserved_object_key' => $this->storage->reservedKey($locked->workspace, $locked),
                'actor_type' => PromotionActorType::Human,
                'actor_user_id' => $actor->id,
                'system_actor_code' => null,
                'operation_kind' => $operation,
                'client_idempotency_key' => $idempotencyKey,
                'request_digest_sha256' => $digest,
            ]);
        });
    }
}
