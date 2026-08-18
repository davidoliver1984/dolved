<?php

declare(strict_types=1);

namespace App\Enums;

enum ContextualisationStatus: string
{
    case Resolved = 'resolved';
    case ClarificationRequired = 'clarification_required';
}
