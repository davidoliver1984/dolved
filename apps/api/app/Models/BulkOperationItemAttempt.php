<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkAttemptStatus;
use App\Enums\BulkAttemptSuccessKind;
use App\Enums\BulkSubordinateIdentityKind;
use App\Enums\BulkSubordinateKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BulkOperationItemAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => BulkAttemptStatus::class,
            'success_kind' => BulkAttemptSuccessKind::class,
            'result_subordinate_kind' => BulkSubordinateKind::class,
            'result_identity_kind' => BulkSubordinateIdentityKind::class,
            'lease_expires_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BulkOperationItem::class, 'bulk_operation_item_id');
    }
}
