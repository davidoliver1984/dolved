<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EvidenceSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_provenance' => 'array'];
    }

    public function generatedAnswer(): BelongsTo
    {
        return $this->belongsTo(GeneratedAnswer::class);
    }

    public function documentChunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function ingestionEventClaim(): BelongsTo
    {
        return $this->belongsTo(IngestionEventClaim::class);
    }

    public function answerParts(): BelongsToMany
    {
        return $this->belongsToMany(AnswerPart::class, 'answer_part_evidence_snapshot');
    }
}
