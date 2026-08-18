<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationDeliveryMode: string
{
    case StreamingParts = 'streaming_parts';
    case CompleteResultOnly = 'complete_result_only';
}
