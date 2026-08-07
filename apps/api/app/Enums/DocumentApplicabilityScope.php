<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentApplicabilityScope: string
{
    case Universal = 'universal';
    case Specific = 'specific';
}
