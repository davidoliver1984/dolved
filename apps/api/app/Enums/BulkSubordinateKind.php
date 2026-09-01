<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkSubordinateKind: string
{
    case PromotionAttempt = 'promotion_attempt';
    case ContentCloneOperation = 'content_clone_operation';
    case FullIngestionFallback = 'full_ingestion_fallback';
}
