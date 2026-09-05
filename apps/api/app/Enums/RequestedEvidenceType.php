<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestedEvidenceType: string
{
    case PolicyOrProceduralRequirements = 'policy_or_procedural_requirements';
    case PersonalRecordStatus = 'personal_record_status';
    case CurrentVersusHistoricalComparison = 'current_versus_historical_comparison';
}
