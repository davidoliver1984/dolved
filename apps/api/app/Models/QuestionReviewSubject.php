<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class QuestionReviewSubject extends Model
{
    protected $guarded = [];

    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(QuestionReview::class, 'question_review_subject_assignments')->withTimestamps();
    }
}
