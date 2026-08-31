<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionAttemptStatus: string
{
    case Reserved = 'reserved';
    case Copying = 'copying';
    case SourceVerified = 'source_verified';
    case Committed = 'committed';
    case Conflict = 'conflict';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Committed,
            self::Conflict,
            self::Failed,
            self::Abandoned,
            self::Expired,
        ], true);
    }
}
