<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Enums\WorkspaceCorpusGenerationStatus;
use Database\Factories\EmbeddingSpaceGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'embedding_profile_id',
    'collection_name',
    'vector_name',
    'dimensions',
    'distance',
    'status',
    'available_at',
    'retired_at',
])]
class EmbeddingSpaceGeneration extends Model
{
    /** @use HasFactory<EmbeddingSpaceGenerationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (EmbeddingSpaceGeneration $generation): void {
            if (
                $generation->exists
                && $generation->isDirty([
                    'public_id',
                    'embedding_profile_id',
                    'collection_name',
                    'vector_name',
                    'dimensions',
                    'distance',
                ])
            ) {
                throw new LogicException(
                    'Embedding-space identity and compatibility are immutable.'
                );
            }

            $profile = EmbeddingProfile::query()->find($generation->embedding_profile_id);
            if ($profile === null || $profile->dimensions !== $generation->dimensions) {
                throw new LogicException(
                    'Embedding-space dimensions must match its profile.'
                );
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
                throw new LogicException(
                    'An embedding space with an active corpus generation must remain available.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'status' => EmbeddingSpaceGenerationStatus::class,
            'available_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EmbeddingProfile, $this>
     */
    public function embeddingProfile(): BelongsTo
    {
        return $this->belongsTo(EmbeddingProfile::class);
    }

    /**
     * @return HasMany<WorkspaceCorpusGeneration, $this>
     */
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
            throw new LogicException(
                'Embedding-space lifecycle timestamps do not match its status.'
            );
        }
    }
}
