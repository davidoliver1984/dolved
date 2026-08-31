<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightRejectionReason;
use App\Enums\ImportPreflightStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class ImportItem extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $item): void {
            if ($item->isDirty(['public_id', 'import_batch_id', 'workspace_id', 'staged_object_key'])) {
                throw new LogicException('Import item identity and staging key are immutable.');
            }
            if ($item->getRawOriginal('preflight_status') === ImportPreflightStatus::Verified->value
                && $item->isDirty(['source_checksum_sha256', 'media_type', 'size_bytes', 'preflight_status'])) {
                throw new LogicException('Verified import source identity is immutable.');
            }
            if ($item->getRawOriginal('replaced_by_import_item_id') !== null
                && $item->isDirty('replaced_by_import_item_id')) {
                throw new LogicException('Import replacement lineage is set once.');
            }
            if ($item->replaced_by_import_item_id !== null
                && (int) $item->replaced_by_import_item_id === (int) $item->id) {
                throw new LogicException('An import item cannot replace itself.');
            }
            if ($item->getRawOriginal('current_decision_snapshot_id') !== null
                && $item->isDirty('current_decision_snapshot_id')
                && ($item->current_decision_snapshot_id === null
                    || (int) $item->current_decision_snapshot_id <= (int) $item->getRawOriginal('current_decision_snapshot_id'))) {
                throw new LogicException('The current decision pointer moves forward only.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'preflight_status' => ImportPreflightStatus::class,
            'preflight_rejection_reason' => ImportPreflightRejectionReason::class,
            'match_status' => ImportMatchStatus::class,
            'size_bytes' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function currentDecisionSnapshot(): BelongsTo
    {
        return $this->belongsTo(ImportDecisionSnapshot::class, 'current_decision_snapshot_id');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_import_item_id');
    }

    public function decisionSnapshots(): HasMany
    {
        return $this->hasMany(ImportDecisionSnapshot::class);
    }

    public function promotionAttempts(): HasMany
    {
        return $this->hasMany(PromotionAttempt::class);
    }
}
