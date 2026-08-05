<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkspaceCorpusGenerationStatus: string
{
    case Building = 'building';
    case Verifying = 'verifying';
    case Active = 'active';
    case Superseded = 'superseded';
    case Retired = 'retired';
}
