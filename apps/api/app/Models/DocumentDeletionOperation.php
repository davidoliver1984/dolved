<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentDeletionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDeletionOperation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DocumentDeletionStatus::class,
            'active_attempt_ids' => 'array',
            'vector_scopes' => 'array',
            'cleanup_evidence' => 'array',
            'lease_generation' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function familyDeletionOperation(): BelongsTo
    {
        return $this->belongsTo(DocumentFamilyDeletionOperation::class, 'document_family_deletion_operation_id');
    }
}
