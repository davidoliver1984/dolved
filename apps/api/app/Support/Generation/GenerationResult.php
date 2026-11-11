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

        $parts = [];
        foreach ($this->answerParts as $index => $part) {
            if (! $this->isUnsupportedBinaryPart($index, $part)) {
                $parts[] = $part->text;
            }
        }
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

        $disclosure = "Unconfirmed requested aspects\n\n".implode("\n", $gaps);

        return $body === '' ? $disclosure : $body."\n\n".$disclosure;
    }

    private function isUnsupportedBinaryPart(int $index, AnswerPartResult $part): bool
    {
        if ($this->outcome !== GenerationOutcome::Qualified
            || preg_match('/^\s*(?:yes|no)\s*[,\x{2013}\x{2014}:!-]/iu', $part->text) !== 1) {
            return false;
        }

        $owners = array_values(array_filter($this->aspectLinks,
            static fn (array $link): bool => in_array($index, $link['answer_part_indices'] ?? [], true)));

        // A binary assertion cannot be shown as the answer to a need whose
        // requested fact the same result explicitly says is unestablished.
        // Do not suppress a part shared with another, independently supported need.
        return count($owners) === 1 && ($owners[0]['unsupported_aspect_indices'] ?? []) !== [];
    }
}
