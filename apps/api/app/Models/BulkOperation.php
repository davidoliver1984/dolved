<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkOperationStatus;
use App\Enums\BulkOperationType;
use App\Enums\BulkSelectionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BulkOperation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'operation_type' => BulkOperationType::class,
            'status' => BulkOperationStatus::class,
            'selection_mode' => BulkSelectionMode::class,
            'payload_schema_version' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'cancellation_requested_at' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkOperationItem::class)->orderBy('ordinal');
    }
}
