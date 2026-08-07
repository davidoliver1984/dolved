<?php

declare(strict_types=1);

namespace App\Enums;

enum RetrievalTemporalMode: string
{
    case Current = 'current';
    case ValidAtDate = 'valid_at_date';
    case Compare = 'compare';
    case ClarificationRequired = 'clarification_required';
}
