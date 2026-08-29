<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentContentCloneStatus: string
{
    case Authorised = 'authorised';
    case Copying = 'copying';
    case Verifying = 'verifying';
    case Indexed = 'indexed';
    case CleanupRequired = 'cleanup_required';
    case FallbackReady = 'fallback_ready';
}
