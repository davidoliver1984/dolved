<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RetrievalClarificationSource;
use App\Enums\RetrievalOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RetrievalOutcomeSnapshot extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $snapshot): void {
            $run = GenerationRun::query()->find($snapshot->generation_run_id);
            if (! $run instanceof GenerationRun || $run->workspace_id !== $snapshot->workspace_id) {
                throw new LogicException('Retrieval snapshot tenancy is inconsistent.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'outcome' => RetrievalOutcome::class,
            'clarification_source' => RetrievalClarificationSource::class,
            'resolved_temporal_authority' => 'array',
            'resolved_applicability_location' => 'array',
            'lineage' => 'array',
            'evaluated_at' => 'immutable_datetime',
            'comparison_state' => 'array',
            'safe_metadata' => 'array',
            'retryable' => 'boolean',
        ];
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }
}
