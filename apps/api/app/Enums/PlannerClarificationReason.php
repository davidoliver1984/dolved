<?php

declare(strict_types=1);

namespace App\Enums;

enum PlannerClarificationReason: string
{
    case UnclassifiableTemporalIntent = 'unclassifiable_temporal_intent';
}
