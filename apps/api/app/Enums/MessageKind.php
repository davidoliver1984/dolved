<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageKind: string
{
    case GroundedAnswer = 'grounded_answer';
    case Clarification = 'clarification';
    case NoAnswer = 'no_answer';
}
