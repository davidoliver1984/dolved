<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportPreflightAttemptStatus;
use App\Enums\ImportPreflightResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ImportPreflightAttempt extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $attempt): void {
            if ($attempt->isDirty(['event_id', 'import_item_id', 'workspace_id', 'lease_generation', 'lease_token_hash', 'lease_expires_at', 'staged_object_key', 'declared_media_type'])) {
                throw new LogicException('Import preflight identity and lease are immutable.');
            }
            if ($attempt->getRawOriginal('status') !== ImportPreflightAttemptStatus::Open->value
                && $attempt->isDirty(['status', 'result', 'diagnostic_code', 'reported_payload_sha256', 'completed_at'])) {
                throw new LogicException('A terminal import preflight attempt is immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ImportPreflightAttemptStatus::class,
            'result' => ImportPreflightResult::class,
            'lease_generation' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
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
}
