<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'workspace_id',
    'workspace_corpus_generation_id',
    'batch_number',
    'request_id',
    'input_count',
    'input_identity_digest',
    'status',
    'point_manifest_digest',
    'completed_at',
])]
class WorkspaceCorpusMaterialisationBatch extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $batch): void {
            if ($batch->exists && $batch->isDirty([
                'public_id',
                'workspace_id',
                'workspace_corpus_generation_id',
                'batch_number',
                'request_id',
                'input_count',
                'input_identity_digest',
            ])) {
                throw new LogicException('Corpus materialisation batch identity is immutable.');
            }
            if ($batch->exists && $batch->getRawOriginal('status') === 'completed' && $batch->isDirty()) {
                throw new LogicException('A completed corpus materialisation batch is immutable.');
            }
            $valid = ($batch->status === 'pending'
                    && $batch->point_manifest_digest === null
                    && $batch->completed_at === null)
                || ($batch->status === 'completed'
                    && $batch->point_manifest_digest !== null
                    && $batch->completed_at !== null);
            if (! $valid) {
                throw new LogicException('Corpus materialisation batch completion evidence is inconsistent.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'batch_number' => 'integer',
            'input_count' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<WorkspaceCorpusGeneration, $this> */
    public function workspaceCorpusGeneration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceCorpusGeneration::class);
    }
}
