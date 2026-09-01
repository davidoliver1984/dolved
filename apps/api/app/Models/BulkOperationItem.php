<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkEligibilityStatus;
use App\Enums\BulkItemStatus;
use App\Enums\BulkOperationType;
use App\Enums\BulkSubordinateIdentityKind;
use App\Enums\BulkSubordinateKind;
use App\Enums\BulkTargetKind;
use App\Enums\BulkTargetReferenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BulkOperationItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'operation_type' => BulkOperationType::class,
            'target_reference_status' => BulkTargetReferenceStatus::class,
            'target_kind' => BulkTargetKind::class,
            'expected_state_snapshot' => 'array',
            'eligibility_status' => BulkEligibilityStatus::class,
            'execution_status' => BulkItemStatus::class,
            'subordinate_kind' => BulkSubordinateKind::class,
            'subordinate_identity_kind' => BulkSubordinateIdentityKind::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'subordinate_awaited_since' => 'immutable_datetime',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(BulkOperation::class, 'bulk_operation_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(DocumentFamily::class, 'target_family_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    public function importItem(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class, 'target_import_item_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(BulkOperationItemAttempt::class)->orderBy('generation');
    }

    public function subordinateTransitions(): HasMany
    {
        return $this->hasMany(BulkOperationItemSubordinateTransition::class)->orderBy('ordinal');
    }
}
