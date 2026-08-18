<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatStreamEventType: string
{
    case RunProgress = 'run_progress';
    case AnswerPartAcceptedForDisplay = 'answer_part_accepted_for_display';
    case AnswerCompleted = 'answer_completed';
    case ClarificationRequired = 'clarification_required';
    case RunFailed = 'run_failed';
    case RunCancelled = 'run_cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::AnswerCompleted, self::ClarificationRequired, self::RunFailed, self::RunCancelled], true);
    }
}
