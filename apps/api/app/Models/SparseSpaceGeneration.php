<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use Database\Factories\SparseSpaceGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'sparse_embedding_profile_id',
    'embedding_space_generation_id',
    'vector_name',
    'status',
    'available_at',
    'retired_at',
])]
class SparseSpaceGeneration extends Model
{
    /** @use HasFactory<SparseSpaceGenerationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (SparseSpaceGeneration $generation): void {
            if ($generation->exists && $generation->isDirty([
                'public_id',
                'sparse_embedding_profile_id',
                'embedding_space_generation_id',
                'vector_name',
            ])) {
                throw new LogicException('Sparse-space identity and compatibility are immutable.');
            }

            $generation->assertLifecycleTimestamps();
            if (
                $generation->exists
                && $generation->isDirty('status')
                && $generation->getRawOriginal('status') === EmbeddingSpaceGenerationStatus::Available->value
                && $generation->status !== EmbeddingSpaceGenerationStatus::Available
                && $generation->workspaceCorpusGenerations()
                    ->where('status', WorkspaceCorpusGenerationStatus::Active->value)
                    ->exists()
            ) {
                throw new LogicException('A sparse space with an active corpus generation must remain available.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => EmbeddingSpaceGenerationStatus::class,
            'available_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SparseEmbeddingProfile, $this> */
    public function sparseEmbeddingProfile(): BelongsTo
    {
        return $this->belongsTo(SparseEmbeddingProfile::class);
    }

    /** @return BelongsTo<EmbeddingSpaceGeneration, $this> */
    public function embeddingSpaceGeneration(): BelongsTo
    {
        return $this->belongsTo(EmbeddingSpaceGeneration::class);
    }

    /** @return HasMany<WorkspaceCorpusGeneration, $this> */
    public function workspaceCorpusGenerations(): HasMany
    {
        return $this->hasMany(WorkspaceCorpusGeneration::class);
    }

    private function assertLifecycleTimestamps(): void
    {
        $valid = match ($this->status) {
            EmbeddingSpaceGenerationStatus::Building,
            EmbeddingSpaceGenerationStatus::Verifying => $this->available_at === null
                && $this->retired_at === null,
            EmbeddingSpaceGenerationStatus::Available,
            EmbeddingSpaceGenerationStatus::Retiring => $this->available_at !== null
                && $this->retired_at === null,
            EmbeddingSpaceGenerationStatus::Retired => $this->retired_at !== null,
        };
        if (! $valid) {
            throw new LogicException('Sparse-space lifecycle timestamps do not match its status.');
        }
    }
}
