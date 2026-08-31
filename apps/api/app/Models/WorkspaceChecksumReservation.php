<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class WorkspaceChecksumReservation extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Workspace checksum reservations are immutable lock identities.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Workspace checksum reservations persist permanently.');
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
