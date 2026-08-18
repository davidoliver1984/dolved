<?php

declare(strict_types=1);

namespace App\Services\Conversation;

use App\Enums\EligibilityClarificationReason;
use App\Enums\MessageKind;
use App\Enums\PlannerClarificationReason;
use App\Enums\RetrievalClarificationSource;
use InvalidArgumentException;

final readonly class ControlledResponseRenderer
{
    /** @return array{kind: MessageKind, text: string, renderer_version: string} */
    public function noAnswer(bool $comparisonIncomplete): array
    {
        return [
            'kind' => MessageKind::NoAnswer,
            'text' => $comparisonIncomplete
                ? "I couldn't find enough available evidence to complete both sides of that comparison."
                : "I couldn't find enough available evidence to answer that question.",
            'renderer_version' => (string) config('conversation.controlled_renderer_version'),
        ];
    }

    /** @return array{kind: MessageKind, text: string, renderer_version: string} */
    public function clarification(RetrievalClarificationSource $source, string $reason): array
    {
        $text = match ($source) {
            RetrievalClarificationSource::Planner => match (PlannerClarificationReason::tryFrom($reason)) {
                PlannerClarificationReason::UnclassifiableTemporalIntent => 'Which date, period, or version should I use for this question?',
                null => null,
            },
            RetrievalClarificationSource::EligibilityResolver => match (EligibilityClarificationReason::tryFrom($reason)) {
                EligibilityClarificationReason::AmbiguousAuthorityWindowForPeriod => 'Which date within that period should I use?',
                EligibilityClarificationReason::UnresolvableTemporalPeriod => 'Which specific date or recognised calendar period should I use?',
                EligibilityClarificationReason::AmbiguousHistoricalReference => 'Which historical version should I use?',
                EligibilityClarificationReason::HistoricalReferenceUnresolved => 'Can you identify the historical version or date you mean?',
                EligibilityClarificationReason::UnresolvedLocationReference => 'Which organisational site or region do you mean?',
                EligibilityClarificationReason::AmbiguousLocationReference => 'Which of the matching organisational locations do you mean?',
                EligibilityClarificationReason::MultipleUnrelatedLocationReferences => 'Which one of those unrelated organisational locations should I use?',
                null => null,
            },
        };
        if ($text === null) {
            throw new InvalidArgumentException('The retrieval clarification reason is unsupported.');
        }

        return [
            'kind' => MessageKind::Clarification,
            'text' => $text,
            'renderer_version' => (string) config('conversation.controlled_renderer_version'),
        ];
    }
}
