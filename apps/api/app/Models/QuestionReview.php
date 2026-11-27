<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class QuestionReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(QuestionReviewSubject::class, 'question_review_subject_assignments')->withTimestamps();
    }
}
