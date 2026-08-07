<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentGovernanceStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Withdrawn = 'withdrawn';
}
