<?php

declare(strict_types=1);

namespace App\Enums;

enum RetrievalTemporalReferenceKind: string
{
    case CalendarPeriod = 'calendar_period';
    case HistoricalReference = 'historical_reference';
}
