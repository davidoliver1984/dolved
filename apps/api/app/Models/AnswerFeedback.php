<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AnswerFeedback extends Model
{
    protected $table = 'answer_feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime'];
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }
}
