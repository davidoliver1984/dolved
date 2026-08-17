<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationSide: string
{
    case Primary = 'primary';
    case Comparison = 'comparison';
}
