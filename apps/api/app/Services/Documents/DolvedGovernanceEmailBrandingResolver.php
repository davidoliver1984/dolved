<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\Documents\ResolveDocumentGovernanceEmailBranding;
use App\Data\Documents\ResolvedGovernanceEmailBranding;

final class DolvedGovernanceEmailBrandingResolver implements ResolveDocumentGovernanceEmailBranding
{
    public function resolve(string $configurationIdentity, string $accentIdentity): ResolvedGovernanceEmailBranding
    {
        return new ResolvedGovernanceEmailBranding(
            brandName: 'Dolved',
            accentColour: '#008466',
        );
    }
}
