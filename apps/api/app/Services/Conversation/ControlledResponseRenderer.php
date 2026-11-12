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
    public function noAnswer(bool $comparisonIncomplete, ?string $reason = null): array
    {
        return [
            'kind' => MessageKind::NoAnswer,
            'text' => $reason === 'planner_validation_failed'
                ? "I couldn't interpret this question reliably, so I haven't used a guessed interpretation to search or answer it. Please try rephrasing your question."
                : ($reason === 'no_previous_version'
                ? 'Only one version of the requested document is available in this knowledge library, so there is no earlier version available to compare.'
                : ($comparisonIncomplete
                ? "I couldn't find enough available evidence to complete both sides of that comparison."
                : "I couldn't find enough available evidence to answer that question.")),
            'renderer_version' => (string) config('conversation.controlled_renderer_version'),
        ];
    }

    /** @return array{kind: MessageKind, text: string, renderer_version: string} */
    public function clarification(RetrievalClarificationSource $source, string $reason, ?string $question = null): array
    {
        $text = match ($source) {
            RetrievalClarificationSource::Planner => match (PlannerClarificationReason::tryFrom($reason)) {
                PlannerClarificationReason::UnclassifiableTemporalIntent => 'Which date, period, or version should I use for this question?',
                PlannerClarificationReason::EvidenceNeedsClarification, PlannerClarificationReason::EvidenceNeedsCapacity => is_string($question) && trim($question) !== '' ? $question : null,
                PlannerClarificationReason::UngroundedNeedsReference => 'I could not reliably link part of your question to what you wrote. Could you rephrase it, making any document, date or location reference explicit?',
                PlannerClarificationReason::InvalidFactDateReference => 'Which date or month do you mean for that event or personal record?',
                PlannerClarificationReason::UngroundedCalendarReference => 'Which date, month or period do you mean for this question?',
                null => null,
            },
            RetrievalClarificationSource::EligibilityResolver => match (EligibilityClarificationReason::tryFrom($reason)) {
                EligibilityClarificationReason::AmbiguousDocumentSubject => 'Which document or policy do you mean?',
                EligibilityClarificationReason::AmbiguousAuthorityWindowForPeriod => 'Which date within that period should I use?',
                EligibilityClarificationReason::UnresolvableTemporalPeriod => 'Which specific date or recognised calendar period should I use?',
                EligibilityClarificationReason::AmbiguousHistoricalReference => 'Which historical version should I use?',
                EligibilityClarificationReason::HistoricalReferenceUnresolved => 'Can you identify the historical version or date you mean?',
                EligibilityClarificationReason::UnresolvedRecordReference => 'Which record do you mean?',
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
