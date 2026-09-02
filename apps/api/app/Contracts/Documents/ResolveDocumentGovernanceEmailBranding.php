<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

use App\Data\Documents\ResolvedGovernanceEmailBranding;

interface ResolveDocumentGovernanceEmailBranding
{
    public function resolve(string $configurationIdentity, string $accentIdentity): ResolvedGovernanceEmailBranding;
}
