<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IngestionAttemptStatus;
use Database\Factories\IngestionEventClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'event_id',
    'workspace_id',
    'document_id',
    'workspace_public_id',
    'document_public_id',
    'correlation_id',
    'payload_sha256',
    'claimed_at',
    'embedding_space_generation_id',
    'sparse_space_generation_id',
    'workspace_corpus_generation_id',
    'status',
    'lease_token_hash',
    'lease_generation',
    'lease_expires_at',
    'expected_chunk_count',
    'chunk_manifest_digest',
    'sealed_at',
    'expected_point_count',
    'point_manifest_digest',
    'embedding_profile_fingerprint',
    'sparse_profile_fingerprint',
    'publication_evidence',
    'publication_authorised_at',
    'completed_at',
    'failed_at',
    'cancelled_at',
    'failure_code',
    'failure_message',
])]
class IngestionEventClaim extends Model
{
    /** @use HasFactory<IngestionEventClaimFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (IngestionEventClaim $claim): void {
            if ($claim->isDirty([
                'event_id', 'workspace_id', 'document_id', 'workspace_public_id',
                'document_public_id', 'correlation_id', 'payload_sha256',
            ])) {
                throw new LogicException('Ingestion attempt identity is immutable.');
            }
            foreach (['embedding_space_generation_id', 'sparse_space_generation_id', 'workspace_corpus_generation_id'] as $field) {
                if ($claim->isDirty($field) && $claim->getOriginal($field) !== null) {
                    throw new LogicException('Ingestion attempt generation identity is immutable.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'immutable_datetime',
            'status' => IngestionAttemptStatus::class,
            'lease_generation' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'expected_chunk_count' => 'integer',
            'expected_point_count' => 'integer',
            'publication_evidence' => 'array',
            'sealed_at' => 'immutable_datetime',
            'publication_authorised_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<EmbeddingSpaceGeneration, $this> */
    public function embeddingSpaceGeneration(): BelongsTo
    {
        return $this->belongsTo(EmbeddingSpaceGeneration::class);
    }

    /** @return BelongsTo<SparseSpaceGeneration, $this> */
    public function sparseSpaceGeneration(): BelongsTo
    {
        return $this->belongsTo(SparseSpaceGeneration::class);
    }

    /** @return BelongsTo<WorkspaceCorpusGeneration, $this> */
    public function workspaceCorpusGeneration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceCorpusGeneration::class);
    }

    /** @return HasMany<DocumentChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    /** @return HasMany<DocumentExtractionUploadAuthorisation, $this> */
    public function extractionUploadAuthorisations(): HasMany
    {
        return $this->hasMany(DocumentExtractionUploadAuthorisation::class);
    }
}
