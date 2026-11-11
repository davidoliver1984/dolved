<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationOutcome;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['outcome' => GenerationOutcome::class, 'unsupported_aspects' => 'array', 'generation_configuration' => 'array', 'usage' => 'array', 'cost_usd' => 'decimal:8', 'aspect_links' => 'array', 'aspect_results' => 'array', 'scope_facts' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'assistant_message_id');
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
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
        if ($this->outcome === GenerationOutcome::InsufficientEvidence) {
            return (string) $this->insufficiency_reason;
        }

        return (new GenerationResult($this->outcome,
            $this->answerParts->map(fn ($part) => new AnswerPartResult($part->text, []))->all(),
            $this->unsupported_aspects, $this->insufficiency_reason,
            aspectLinks: $this->aspect_links ?? [], aspectResults: $this->aspect_results ?? [],
            scopeFacts: $this->scope_facts ?? []))->renderedText();
    }
}
