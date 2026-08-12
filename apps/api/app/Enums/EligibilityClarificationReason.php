<?php

declare(strict_types=1);

namespace App\Enums;

enum EligibilityClarificationReason: string
{
    case AmbiguousAuthorityWindowForPeriod = 'ambiguous_authority_window_for_period';
    case UnresolvableTemporalPeriod = 'unresolvable_temporal_period';
    case AmbiguousHistoricalReference = 'ambiguous_historical_reference';
    case HistoricalReferenceUnresolved = 'historical_reference_unresolved';
    case UnresolvedLocationReference = 'unresolved_location_reference';
    case AmbiguousLocationReference = 'ambiguous_location_reference';
    case MultipleUnrelatedLocationReferences = 'multiple_unrelated_location_references';
}
