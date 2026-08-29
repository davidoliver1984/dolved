<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExtractionProjectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'public_id', 'workspace_id', 'document_id', 'document_extraction_artifact_id',
    'status', 'expected_element_count', 'expected_warning_count',
    'expected_projection_manifest_digest', 'expected_warning_manifest_digest',
    'verified_projection_manifest_digest', 'verified_warning_manifest_digest',
    'source_extractor', 'normaliser', 'metadata', 'changes', 'failure_code',
    'verified_at', 'published_at', 'retired_at', 'failed_at',
])]
class DocumentExtractionProjectionGeneration extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $generation): void {
            if ($generation->isDirty([
                'public_id', 'workspace_id', 'document_id', 'document_extraction_artifact_id',
                'expected_element_count', 'expected_warning_count',
                'expected_projection_manifest_digest', 'expected_warning_manifest_digest',
            ])) {
                throw new LogicException('Extraction projection generation identity is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ExtractionProjectionStatus::class,
            'expected_element_count' => 'integer',
            'expected_warning_count' => 'integer',
            'source_extractor' => 'array',
            'normaliser' => 'array',
            'metadata' => 'array',
            'changes' => 'array',
            'verified_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(DocumentExtractionArtifact::class, 'document_extraction_artifact_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(DocumentExtractionProjectionElement::class, 'projection_generation_id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(DocumentExtractionProjectionWarning::class, 'projection_generation_id');
    }
}
