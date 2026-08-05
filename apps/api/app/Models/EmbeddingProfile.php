<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmbeddingProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'fingerprint',
    'provider',
    'model',
    'dimensions',
    'output_dtype',
    'document_input_type',
    'query_input_type',
    'normalisation',
    'truncation',
    'model_revision',
    'adapter_version',
])]
class EmbeddingProfile extends Model
{
    /** @use HasFactory<EmbeddingProfileFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (EmbeddingProfile $profile): void {
            if ($profile->isDirty()) {
                throw new LogicException('An embedding profile is immutable.');
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
            'truncation' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmbeddingSpaceGeneration, $this>
     */
    public function embeddingSpaceGenerations(): HasMany
    {
        return $this->hasMany(EmbeddingSpaceGeneration::class);
    }
}
