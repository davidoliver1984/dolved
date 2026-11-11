<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Enums\GenerationOutcome;
use App\Support\Retrieval\ApplicabilityScopeFact;

final readonly class GenerationResult
{
    /** @param list<AnswerPartResult> $answerParts @param list<string> $unsupportedAspects @param array<string, mixed>|null $usage */
    public function __construct(
        public GenerationOutcome $outcome,
        public array $answerParts,
        public array $unsupportedAspects,
        public ?string $insufficiencyReason,
        public ?array $usage = null,
        public array $aspectLinks = [],
        public array $aspectResults = [],
        public array $scopeFacts = [],
        public ?self $operationalResult = null,
    ) {}

    public function renderedText(): string
    {
        $scope = array_map(fn (array $fact): string => (new ApplicabilityScopeFact($fact))->renderedText(), $this->scopeFacts);

        $parts = array_map(fn (AnswerPartResult $part): string => $part->text, $this->answerParts);
        if ($scope !== [] && $parts === [] && $this->unsupportedAspects !== []) {
            $parts[] = 'No operational evidence was available for the other requested aspect. The scope statement does not answer that operational question.';
        }

        $body = $scope !== [] && $parts !== []
            ? "Recorded policy scope\n\n".implode("\n\n", $scope)."\n\nOther requested guidance\n\n".implode("\n\n", $parts)
            : implode("\n\n", [...$scope, ...$parts]);

        if ($this->outcome !== GenerationOutcome::Qualified || $this->unsupportedAspects === []) {
            return $body;
        }

        $gaps = array_map(fn (string $aspect): string => '- '.trim($aspect), $this->unsupportedAspects);

        return $body."\n\nUnconfirmed requested aspects\n\n".implode("\n", $gaps);
    }
}
