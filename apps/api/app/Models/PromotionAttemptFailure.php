<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PromotionAttemptFailure extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Promotion attempt failures are immutable.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lease_generation' => 'integer',
            'safe_context' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PromotionAttempt::class, 'promotion_attempt_id');
    }
}
