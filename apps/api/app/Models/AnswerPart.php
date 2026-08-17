<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnswerPart extends Model
{
    protected $guarded = [];

    public function generatedAnswer(): BelongsTo
    {
        return $this->belongsTo(GeneratedAnswer::class);
    }

    public function evidenceSnapshots(): BelongsToMany
    {
        return $this->belongsToMany(EvidenceSnapshot::class, 'answer_part_evidence_snapshot')->withPivot('ordinal')->orderByPivot('ordinal');
    }
}
