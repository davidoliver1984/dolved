<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkSelectionMode: string
{
    case CurrentPage = 'current_page';
    case AllFiltered = 'all_filtered';
}
