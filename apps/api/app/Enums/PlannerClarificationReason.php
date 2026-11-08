<?php

declare(strict_types=1);

namespace App\Enums;

enum PlannerClarificationReason: string
{
    case UnclassifiableTemporalIntent = 'unclassifiable_temporal_intent';
    case EvidenceNeedsClarification = 'evidence_needs_clarification';
    case UngroundedNeedsReference = 'ungrounded_needs_reference';
    case InvalidFactDateReference = 'invalid_fact_date_reference';
    case UngroundedCalendarReference = 'ungrounded_calendar_reference';
}
