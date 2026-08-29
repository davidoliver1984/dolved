<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChecksumUnavailableReason;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'source_filename',
    'publisher_label',
    'source_url',
    'media_type',
    'size_bytes',
    'failure_category',
    'failure_message',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Document $document): void {
            if ($document->exists && $document->isDirty('public_id')) {
                throw new LogicException('A document public ID is immutable.');
            }

            if ($document->exists && $document->isDirty('workspace_id')) {
                throw new LogicException('Document workspace ownership is immutable.');
            }

            if ($document->exists && $document->isDirty(['document_family_id', 'predecessor_document_id'])) {
                throw new LogicException('Document family and version lineage are immutable.');
            }

            if ($document->exists && $document->isDirty('created_by_user_id')) {
                throw new LogicException('Document creation provenance is immutable.');
            }

            if ($document->exists && $document->isDirty('storage_key')) {
                throw new LogicException('A document storage key is immutable.');
            }

            if (
                $document->exists
                && $document->isDirty([
                    'source_filename',
                    'publisher_label',
                    'source_url',
                    'media_type',
                    'size_bytes',
                ])
            ) {
                throw new LogicException(
                    'Document source metadata is immutable after creation.'
                );
            }

            if (
                $document->exists
                && $document->getRawOriginal('checksum_verification_status') === ChecksumVerificationStatus::Verified->value
                && $document->isDirty([
                    'source_checksum_sha256',
                    'checksum_verification_status',
                    'checksum_unavailable_reason',
                ])
            ) {
                throw new LogicException('A verified document checksum is immutable.');
            }

            if (
                $document->status === DocumentStatus::Failed
                && (
                    blank($document->failure_category)
                    || blank($document->failure_message)
                )
            ) {
                throw new LogicException(
                    'A failed document requires a failure category and message.'
                );
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'governance_status' => DocumentGovernanceStatus::class,
            'checksum_verification_status' => ChecksumVerificationStatus::class,
            'checksum_unavailable_reason' => ChecksumUnavailableReason::class,
            'size_bytes' => 'integer',
            'effective_from' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<DocumentFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(DocumentFamily::class, 'document_family_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_document_id');
    }

    /** @return HasOne<Document, $this> */
    public function successor(): HasOne
    {
        return $this->hasOne(self::class, 'predecessor_document_id');
    }

    /** @return HasOne<DocumentApplicabilitySnapshot, $this> */
    public function applicabilitySnapshot(): HasOne
    {
        return $this->hasOne(DocumentApplicabilitySnapshot::class);
    }

    /** @return HasMany<DocumentGovernanceAuditEvent, $this> */
    public function governanceAuditEvents(): HasMany
    {
        return $this->hasMany(DocumentGovernanceAuditEvent::class);
    }

    /**
     * @return HasMany<DocumentChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function ingestionAttempts(): HasMany
    {
        return $this->hasMany(IngestionEventClaim::class);
    }

    public function latestIngestionAttempt(): HasOne
    {
        return $this->hasOne(IngestionEventClaim::class)->latestOfMany();
    }

    public function ingestionRetries(): HasMany
    {
        return $this->hasMany(DocumentIngestionRetry::class);
    }

    public function deletionOperation(): HasOne
    {
        return $this->hasOne(DocumentDeletionOperation::class);
    }
}
