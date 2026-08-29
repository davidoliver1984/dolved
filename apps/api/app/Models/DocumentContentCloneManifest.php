<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentCloneManifestStatus;
use App\Enums\ExtractionUploadCleanupState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'public_id', 'document_content_clone_operation_id', 'ingestion_event_claim_id',
    'lease_generation', 'object_key', 'schema_version', 'entry_count', 'size_bytes',
    'checksum_sha256', 'status', 'expires_at', 'verified_at', 'consumed_at',
    'cleanup_state', 'cleanup_attempt_count', 'cleanup_claimed_at',
    'cleanup_last_attempted_at', 'cleanup_error_code',
])]
final class DocumentContentCloneManifest extends Model
{
    protected static function booted(): void
    {
        self::updating(function (self $manifest): void {
            if ($manifest->isDirty([
                'public_id', 'document_content_clone_operation_id',
                'ingestion_event_claim_id', 'lease_generation', 'object_key',
                'schema_version', 'entry_count', 'size_bytes', 'checksum_sha256',
            ])) {
                throw new LogicException('Content-clone manifest identity is immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lease_generation' => 'integer',
            'entry_count' => 'integer',
            'size_bytes' => 'integer',
            'status' => ContentCloneManifestStatus::class,
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'cleanup_state' => ExtractionUploadCleanupState::class,
            'cleanup_attempt_count' => 'integer',
            'cleanup_claimed_at' => 'immutable_datetime',
            'cleanup_last_attempted_at' => 'immutable_datetime',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(DocumentContentCloneOperation::class, 'document_content_clone_operation_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(IngestionEventClaim::class, 'ingestion_event_claim_id');
    }
}
