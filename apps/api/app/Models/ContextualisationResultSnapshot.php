<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContextualisationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ContextualisationResultSnapshot extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $snapshot): void {
            $run = GenerationRun::query()->find($snapshot->generation_run_id);
            if (! $run instanceof GenerationRun || $run->workspace_id !== $snapshot->workspace_id) {
                throw new LogicException('Contextualisation snapshot tenancy is inconsistent.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ContextualisationStatus::class,
            'used_prior_context' => 'boolean',
            'interpretation_metadata' => 'array',
            'usage' => 'array',
        ];
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }
}
