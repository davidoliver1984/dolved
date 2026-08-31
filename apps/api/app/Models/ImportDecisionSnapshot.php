<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class ImportDecisionSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Import decision snapshots are immutable.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Import decision snapshots are retained lineage.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['schema_version' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class, 'import_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function promotionAttempts(): HasMany
    {
        return $this->hasMany(PromotionAttempt::class, 'decision_snapshot_id');
    }
}
