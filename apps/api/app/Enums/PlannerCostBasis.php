<?php

declare(strict_types=1);

namespace App\Enums;

enum PlannerCostBasis: string
{
    case ProviderReported = 'provider_reported';
    case Estimated = 'estimated';
    case Unavailable = 'unavailable';
    case ZeroCostLocal = 'zero_cost_local';
}
