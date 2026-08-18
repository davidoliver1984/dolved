<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationRunStatus: string
{
    case Queued = 'queued';
    case Contextualising = 'contextualising';
    case Retrieving = 'retrieving';
    case PreparingEvidence = 'preparing_evidence';
    case Generating = 'generating';
    case Validating = 'validating';
    case Completed = 'completed';
    case RetrievalNoAnswer = 'retrieval_no_answer';
    case ClarificationRequired = 'clarification_required';
    case Failed = 'failed';
    case CancellationRequested = 'cancellation_requested';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::RetrievalNoAnswer,
            self::ClarificationRequired,
            self::Failed,
            self::Cancelled,
        ], true);
    }

    public function isRetryEligible(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled], true);
    }
}
