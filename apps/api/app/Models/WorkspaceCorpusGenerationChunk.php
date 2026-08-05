<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkspaceCorpusGenerationChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'workspace_id',
    'workspace_corpus_generation_id',
    'document_chunk_id',
])]
class WorkspaceCorpusGenerationChunk extends Model
{
    /** @use HasFactory<WorkspaceCorpusGenerationChunkFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (WorkspaceCorpusGenerationChunk $assignment): void {
            if ($assignment->isDirty()) {
                throw new LogicException('A corpus chunk assignment is immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<WorkspaceCorpusGeneration, $this>
     */
    public function workspaceCorpusGeneration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceCorpusGeneration::class);
    }

    /**
     * @return BelongsTo<DocumentChunk, $this>
     */
    public function documentChunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class);
    }
}
