<?php

declare(strict_types=1);

namespace App\Enums;

enum RetrievalClarificationSource: string
{
    case Planner = 'planner';
    case EligibilityResolver = 'eligibility_resolver';
}
