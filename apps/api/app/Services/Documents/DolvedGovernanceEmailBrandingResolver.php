<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\Documents\ResolveDocumentGovernanceEmailBranding;
use App\Data\Documents\ResolvedGovernanceEmailBranding;
use App\Models\WorkspaceBrandingRevision;

final class DolvedGovernanceEmailBrandingResolver implements ResolveDocumentGovernanceEmailBranding
{
    public function resolve(string $configurationIdentity, string $accentIdentity): ResolvedGovernanceEmailBranding
    {
        if (str_starts_with($configurationIdentity, 'workspace-branding:')) {
            $revision = WorkspaceBrandingRevision::query()
                ->where('public_id', substr($configurationIdentity, strlen('workspace-branding:')))
                ->first();
            $configuration = $revision?->configuration;
            if (is_array($configuration)) {
                return new ResolvedGovernanceEmailBranding(
                    brandName: (string) ($configuration['display_name'] ?? 'Dolved'),
                    accentColour: (string) ($configuration['accent_colour'] ?? '#008466'),
                    logoUrl: is_array($configuration['primary_logo'] ?? null) ? ($configuration['primary_logo']['url'] ?? null) : null,
                    senderDisplayName: $configuration['sender_display_name'] ?? null,
                    footerText: $configuration['footer_text'] ?? null,
                    supportContact: $configuration['support_contact'] ?? null,
                    accentForeground: (string) ($configuration['accent_foreground'] ?? '#FFFFFF'),
                    linkColour: (string) ($configuration['primary_colour'] ?? '#006C4D'),
                );
            }
        }

        return new ResolvedGovernanceEmailBranding(
            brandName: 'Dolved',
            accentColour: '#008466',
        );
    }
}
