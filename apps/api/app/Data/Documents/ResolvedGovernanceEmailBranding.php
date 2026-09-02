<?php

declare(strict_types=1);

namespace App\Data\Documents;

final readonly class ResolvedGovernanceEmailBranding
{
    public function __construct(
        public string $brandName,
        public string $accentColour,
        public ?string $logoUrl = null,
    ) {}
}
