<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Enums\GenerationDeliveryMode;

final readonly class GenerationProfile
{
    /** @param array<string, mixed> $qualityAffectingConfiguration */
    public function __construct(
        public string $provider,
        public string $model,
        public string $contractVersion,
        public string $promptVersion,
        public string $adapterVersion,
        public GenerationDeliveryMode $deliveryMode,
        public array $qualityAffectingConfiguration,
    ) {}
}
