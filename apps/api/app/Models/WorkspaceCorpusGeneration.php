<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use Database\Factories\WorkspaceCorpusGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'workspace_id',
    'embedding_space_generation_id',
    'rebuilt_from_generation_id',
    'rebuild_event_id',
    'sparse_space_generation_id',
    'expected_point_count',
    'point_manifest_digest',
    'verified_at',
    'status',
    'activated_at',
    'superseded_at',
    'retired_at',
])]
class WorkspaceCorpusGeneration extends Model
{
    /** @use HasFactory<WorkspaceCorpusGenerationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (WorkspaceCorpusGeneration $generation): void {
            if (
                $generation->exists
                && $generation->isDirty([
                    'public_id',
                    'workspace_id',
                    'embedding_space_generation_id',
                    'rebuilt_from_generation_id',
                    'rebuild_event_id',
                    'sparse_space_generation_id',
                ])
            ) {
                throw new LogicException(
                    'Workspace corpus ownership and vector-space lineage are immutable.'
                );
            }

            $generation->assertLifecycleTimestamps();

            if ($generation->status === WorkspaceCorpusGenerationStatus::Active) {
                $embeddingSpace = EmbeddingSpaceGeneration::query()
                    ->find($generation->embedding_space_generation_id);
                if (
                    $embeddingSpace === null
                    || $embeddingSpace->status !== EmbeddingSpaceGenerationStatus::Available
                ) {
                    throw new LogicException(
                        'An active corpus generation requires an available embedding space.'
                    );
                }

                if ($generation->sparse_space_generation_id !== null) {
                    $sparseSpace = SparseSpaceGeneration::query()
                        ->find($generation->sparse_space_generation_id);
                    if (
                        $sparseSpace === null
                        || $sparseSpace->status !== EmbeddingSpaceGenerationStatus::Available
                        || $sparseSpace->embedding_space_generation_id !== $generation->embedding_space_generation_id
                        || $generation->verified_at === null
                    ) {
                        throw new LogicException(
                            'An active hybrid corpus generation requires a compatible available sparse space.'
                        );
                    }
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
            'status' => WorkspaceCorpusGenerationStatus::class,
            'activated_at' => 'datetime',
            'superseded_at' => 'datetime',
            'retired_at' => 'datetime',
            'expected_point_count' => 'integer',
            'verified_at' => 'immutable_datetime',
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
     * @return BelongsTo<EmbeddingSpaceGeneration, $this>
     */
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
    public function rebuiltFromGeneration(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rebuilt_from_generation_id');
    }

    /** @return HasMany<WorkspaceCorpusGeneration, $this> */
    public function rebuiltGenerations(): HasMany
    {
        return $this->hasMany(self::class, 'rebuilt_from_generation_id');
    }

    /**
     * @return HasMany<WorkspaceCorpusGenerationChunk, $this>
     */
    public function chunkAssignments(): HasMany
    {
        return $this->hasMany(WorkspaceCorpusGenerationChunk::class);
    }

    /** @return HasMany<WorkspaceCorpusMaterialisationBatch, $this> */
    public function materialisationBatches(): HasMany
    {
        return $this->hasMany(WorkspaceCorpusMaterialisationBatch::class);
    }

    /**
     * @return BelongsToMany<DocumentChunk, $this>
     */
    public function documentChunks(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentChunk::class,
            'workspace_corpus_generation_chunks'
        )->withTimestamps();
    }

    private function assertLifecycleTimestamps(): void
    {
        $valid = match ($this->status) {
            WorkspaceCorpusGenerationStatus::Building,
            WorkspaceCorpusGenerationStatus::Verifying => $this->activated_at === null
                && $this->superseded_at === null
                && $this->retired_at === null,
            WorkspaceCorpusGenerationStatus::Active => $this->activated_at !== null
                && $this->superseded_at === null
                && $this->retired_at === null,
            WorkspaceCorpusGenerationStatus::Superseded => $this->activated_at !== null
                && $this->superseded_at !== null
                && $this->retired_at === null,
            WorkspaceCorpusGenerationStatus::Retired => $this->retired_at !== null,
        };

        if (! $valid) {
            throw new LogicException(
                'Workspace corpus lifecycle timestamps do not match its status.'
            );
        }
    }
}
