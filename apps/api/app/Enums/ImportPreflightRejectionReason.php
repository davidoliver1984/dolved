<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportPreflightRejectionReason: string
{
    case PasswordProtected = 'password_protected';
    case Encrypted = 'encrypted';
    case CorruptStructure = 'corrupt_structure';
    case MimeMismatch = 'mime_mismatch';
}
