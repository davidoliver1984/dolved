<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Enums\GenerationOutcome;

final readonly class GenerationResult
{
    /** @param list<AnswerPartResult> $answerParts @param list<string> $unsupportedAspects @param array<string, mixed>|null $usage */
    public function __construct(
        public GenerationOutcome $outcome,
        public array $answerParts,
        public array $unsupportedAspects,
        public ?string $insufficiencyReason,
        public ?array $usage = null,
    ) {}

    public function renderedText(): string
    {
        return implode("\n\n", array_map(fn (AnswerPartResult $part): string => $part->text, $this->answerParts));
    }
}
