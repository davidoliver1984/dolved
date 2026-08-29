<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'public_id', 'workspace_id', 'document_id', 'upload_authorisation_id',
    'object_key', 'contract_version', 'artifact_sha256', 'size_bytes',
    'projection_manifest_digest', 'warning_manifest_digest', 'element_count',
    'warning_count', 'storage_version_id', 'storage_etag', 'verified_at', 'published_at',
])]
class DocumentExtractionArtifact extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $artifact): void {
            if ($artifact->isDirty([
                'public_id', 'workspace_id', 'document_id', 'upload_authorisation_id',
                'object_key', 'contract_version', 'artifact_sha256', 'size_bytes',
                'projection_manifest_digest', 'warning_manifest_digest', 'element_count',
                'warning_count', 'storage_version_id', 'storage_etag', 'verified_at',
            ])) {
                throw new LogicException('Document extraction artifact identity is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'element_count' => 'integer',
            'warning_count' => 'integer',
            'verified_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
