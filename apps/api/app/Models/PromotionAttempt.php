<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionActorType;
use App\Enums\PromotionAttemptStatus;
use App\Enums\PromotionOperationKind;
use App\Enums\PromotionSystemActorCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class PromotionAttempt extends Model
{
    protected $guarded = ['actor_identity', 'failure_count'];

    protected static function booted(): void
    {
        self::updating(function (self $attempt): void {
            if ($attempt->isDirty([
                'public_id', 'import_item_id', 'workspace_id', 'decision_snapshot_id',
                'attempt_ordinal', 'reserved_object_key', 'actor_type', 'actor_user_id',
                'system_actor_code', 'operation_kind', 'client_idempotency_key',
                'request_digest_sha256', 'actor_identity', 'failure_count',
            ])) {
                throw new LogicException('Promotion attempt identity and derived values are immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PromotionAttemptStatus::class,
            'checksum_evidence' => 'array',
            'lease_generation' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'failure_count' => 'integer',
            'cancellation_requested_at' => 'immutable_datetime',
            'actor_type' => PromotionActorType::class,
            'system_actor_code' => PromotionSystemActorCode::class,
            'operation_kind' => PromotionOperationKind::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class, 'import_item_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function decisionSnapshot(): BelongsTo
    {
        return $this->belongsTo(ImportDecisionSnapshot::class, 'decision_snapshot_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function failures(): HasMany
    {
        return $this->hasMany(PromotionAttemptFailure::class);
    }

    public function committedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'committed_document_id');
    }
}
