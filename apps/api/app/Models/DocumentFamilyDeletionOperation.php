<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentFamilyDeletionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class DocumentFamilyDeletionOperation extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $operation): void {
            if ($operation->isDirty([
                'public_id', 'workspace_id', 'document_family_id',
                'requested_by_user_id', 'idempotency_key',
                'confirmation_state_digest', 'version_snapshot', 'child_count',
            ])) {
                throw new LogicException('Document-family deletion identity is immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DocumentFamilyDeletionStatus::class,
            'version_snapshot' => 'array',
            'child_count' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(DocumentFamily::class, 'document_family_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DocumentDeletionOperation::class);
    }
}
