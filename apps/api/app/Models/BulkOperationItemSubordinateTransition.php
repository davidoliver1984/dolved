<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkSubordinateIdentityKind;
use App\Enums\BulkSubordinateKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BulkOperationItemSubordinateTransition extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subordinate_kind' => BulkSubordinateKind::class,
            'subordinate_identity_kind' => BulkSubordinateIdentityKind::class,
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BulkOperationItem::class, 'bulk_operation_item_id');
    }
}
