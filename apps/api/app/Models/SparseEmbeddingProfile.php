<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SparseEmbeddingProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'fingerprint',
    'provider',
    'model',
    'tokenizer',
    'tokenizer_revision',
    'output_representation',
    'max_input_tokens',
    'document_input_type',
    'query_input_type',
    'model_revision',
    'adapter_version',
])]
class SparseEmbeddingProfile extends Model
{
    /** @use HasFactory<SparseEmbeddingProfileFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (SparseEmbeddingProfile $profile): void {
            if ($profile->isDirty()) {
                throw new LogicException('A sparse embedding profile is immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['max_input_tokens' => 'integer'];
    }

    /** @return HasMany<SparseSpaceGeneration, $this> */
    public function sparseSpaceGenerations(): HasMany
    {
        return $this->hasMany(SparseSpaceGeneration::class);
    }
}
