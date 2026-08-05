<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'workspace_id',
    'document_id',
    'ordinal',
    'text',
    'token_count',
    'strategy_name',
    'strategy_version',
    'configuration',
    'configuration_fingerprint',
    'provenance',
])]
class DocumentChunk extends Model
{
    /** @use HasFactory<DocumentChunkFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (DocumentChunk $chunk): void {
            if ($chunk->isDirty()) {
                throw new LogicException('A canonical document chunk is immutable.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'token_count' => 'integer',
            'configuration' => 'array',
            'provenance' => 'array',
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
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasMany<WorkspaceCorpusGenerationChunk, $this>
     */
    public function corpusAssignments(): HasMany
    {
        return $this->hasMany(WorkspaceCorpusGenerationChunk::class);
    }

    /**
     * @return BelongsToMany<WorkspaceCorpusGeneration, $this>
     */
    public function workspaceCorpusGenerations(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspaceCorpusGeneration::class,
            'workspace_corpus_generation_chunks'
        )->withTimestamps();
    }
}
