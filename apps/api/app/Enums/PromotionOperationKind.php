<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionOperationKind: string
{
    case Promote = 'promote';
    case Retry = 'retry';
    case Adopt = 'adopt';
}
