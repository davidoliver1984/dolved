<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionActorType: string
{
    case Human = 'human';
    case System = 'system';
}
