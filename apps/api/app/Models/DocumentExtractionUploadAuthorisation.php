<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExtractionUploadCleanupState;
use App\Enums\ExtractionUploadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'public_id', 'workspace_id', 'document_id', 'ingestion_event_claim_id',
    'purpose', 'object_key', 'lease_generation', 'contract_version', 'expires_at',
    'status', 'artifact_sha256', 'size_bytes', 'projection_manifest_digest',
    'warning_manifest_digest', 'element_count', 'warning_count', 'storage_version_id',
    'storage_etag', 'verified_at', 'cleanup_state', 'cleanup_attempt_count',
    'cleanup_claimed_at', 'cleanup_last_attempted_at', 'cleanup_error_code',
])]
class DocumentExtractionUploadAuthorisation extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if ($record->isDirty([
                'public_id', 'workspace_id', 'document_id', 'ingestion_event_claim_id',
                'purpose', 'object_key', 'lease_generation', 'contract_version',
            ])) {
                throw new LogicException('Extraction upload authorisation identity is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'lease_generation' => 'integer',
            'expires_at' => 'immutable_datetime',
            'status' => ExtractionUploadStatus::class,
            'size_bytes' => 'integer',
            'element_count' => 'integer',
            'warning_count' => 'integer',
            'verified_at' => 'immutable_datetime',
            'cleanup_state' => ExtractionUploadCleanupState::class,
            'cleanup_attempt_count' => 'integer',
            'cleanup_claimed_at' => 'immutable_datetime',
            'cleanup_last_attempted_at' => 'immutable_datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(IngestionEventClaim::class, 'ingestion_event_claim_id');
    }

    public function artifact(): HasOne
    {
        return $this->hasOne(DocumentExtractionArtifact::class, 'upload_authorisation_id');
    }
}
