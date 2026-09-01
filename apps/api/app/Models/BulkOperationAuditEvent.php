<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BulkOperationAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['safe_context' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(BulkOperation::class, 'bulk_operation_id');
    }
}
