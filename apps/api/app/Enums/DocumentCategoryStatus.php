<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentCategoryStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
