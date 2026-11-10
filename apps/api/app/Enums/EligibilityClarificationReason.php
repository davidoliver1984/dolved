<?php

declare(strict_types=1);

namespace App\Enums;

enum EligibilityClarificationReason: string
{
    case AmbiguousDocumentSubject = 'ambiguous_document_subject';
    case AmbiguousAuthorityWindowForPeriod = 'ambiguous_authority_window_for_period';
    case UnresolvableTemporalPeriod = 'unresolvable_temporal_period';
    case AmbiguousHistoricalReference = 'ambiguous_historical_reference';
    case HistoricalReferenceUnresolved = 'historical_reference_unresolved';
    case UnresolvedRecordReference = 'unresolved_record_reference';
}
