<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentContentCloneStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'public_id', 'workspace_id', 'source_document_id', 'target_document_id',
    'source_ingestion_event_claim_id', 'target_ingestion_event_claim_id', 'status',
    'materialisation_pipeline_fingerprint', 'materialisation_pipeline_components',
    'source_checksum_sha256', 'expected_point_count', 'expected_point_manifest_digest',
    'verified_point_count', 'verified_point_manifest_digest', 'layer_evidence',
    'failure_code', 'failure_message', 'authorised_at', 'copying_at', 'verifying_at',
    'indexed_at', 'cleanup_required_at', 'fallback_ready_at',
])]
final class DocumentContentCloneOperation extends Model
{
    protected static function booted(): void
    {
        self::updating(function (self $operation): void {
            if ($operation->isDirty([
                'public_id', 'workspace_id', 'source_document_id', 'target_document_id',
                'source_ingestion_event_claim_id', 'target_ingestion_event_claim_id',
                'materialisation_pipeline_fingerprint', 'materialisation_pipeline_components',
                'source_checksum_sha256',
            ])) {
                throw new LogicException('Content-clone identity is immutable.');
            }

            if ($operation->isDirty('status')) {
                $from = DocumentContentCloneStatus::from((string) $operation->getRawOriginal('status'));
                $to = $operation->status;
                $allowed = match ($from) {
                    DocumentContentCloneStatus::Authorised => [
                        DocumentContentCloneStatus::Copying,
                        DocumentContentCloneStatus::CleanupRequired,
                    ],
                    DocumentContentCloneStatus::Copying => [
                        DocumentContentCloneStatus::Verifying,
                        DocumentContentCloneStatus::CleanupRequired,
                    ],
                    DocumentContentCloneStatus::Verifying => [
                        DocumentContentCloneStatus::Indexed,
                        DocumentContentCloneStatus::CleanupRequired,
                    ],
                    DocumentContentCloneStatus::CleanupRequired => [
                        DocumentContentCloneStatus::FallbackReady,
                    ],
                    DocumentContentCloneStatus::Indexed,
                    DocumentContentCloneStatus::FallbackReady => [],
                };
                if (! in_array($to, $allowed, true)) {
                    throw new LogicException("Invalid content-clone transition from {$from->value} to {$to->value}.");
                }
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DocumentContentCloneStatus::class,
            'materialisation_pipeline_components' => 'array',
            'expected_point_count' => 'integer',
            'verified_point_count' => 'integer',
            'layer_evidence' => 'array',
            'authorised_at' => 'immutable_datetime',
            'copying_at' => 'immutable_datetime',
            'verifying_at' => 'immutable_datetime',
            'indexed_at' => 'immutable_datetime',
            'cleanup_required_at' => 'immutable_datetime',
            'fallback_ready_at' => 'immutable_datetime',
        ];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    public function sourceAttempt(): BelongsTo
    {
        return $this->belongsTo(IngestionEventClaim::class, 'source_ingestion_event_claim_id');
    }

    public function targetAttempt(): BelongsTo
    {
        return $this->belongsTo(IngestionEventClaim::class, 'target_ingestion_event_claim_id');
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(DocumentContentCloneManifest::class);
    }
}
