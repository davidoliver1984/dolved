<?php

declare(strict_types=1);

namespace App\Enums;

enum EmbeddingSpaceGenerationStatus: string
{
    case Building = 'building';
    case Verifying = 'verifying';
    case Available = 'available';
    case Retiring = 'retiring';
    case Retired = 'retired';
}
