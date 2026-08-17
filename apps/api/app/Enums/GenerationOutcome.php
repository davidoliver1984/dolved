<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationOutcome: string
{
    case Answered = 'answered';
    case Qualified = 'qualified';
    case InsufficientEvidence = 'insufficient_evidence';
}
