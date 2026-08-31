<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class ImportBatch extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $batch): void {
            if ($batch->isDirty(['public_id', 'workspace_id', 'initiated_by_user_id', 'retention_expires_at'])) {
                throw new LogicException('Import batch identity and retention boundary are immutable.');
            }
            if ($batch->getRawOriginal('status') === ImportBatchStatus::Expired->value && $batch->isDirty('status')) {
                throw new LogicException('An expired import batch is terminal.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'retention_expires_at' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }
}
