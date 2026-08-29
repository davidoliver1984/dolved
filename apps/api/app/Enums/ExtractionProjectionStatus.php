<?php

declare(strict_types=1);

namespace App\Enums;

enum ExtractionProjectionStatus: string
{
    case Building = 'building';
    case Published = 'published';
    case Retired = 'retired';
    case Failed = 'failed';
}
