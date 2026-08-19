<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkspaceInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
