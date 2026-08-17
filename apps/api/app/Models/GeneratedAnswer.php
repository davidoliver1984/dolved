<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['outcome' => GenerationOutcome::class, 'unsupported_aspects' => 'array', 'generation_configuration' => 'array', 'usage' => 'array', 'cost_usd' => 'decimal:8'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function answerParts(): HasMany
    {
        return $this->hasMany(AnswerPart::class)->orderBy('ordinal');
    }

    public function evidenceSnapshots(): HasMany
    {
        return $this->hasMany(EvidenceSnapshot::class);
    }

    public function renderedText(): string
    {
        return $this->answerParts()
            ->pluck('text')
            ->implode("\n\n");
    }
}
