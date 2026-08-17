<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Support\Generation\GenerationProfile;

final class GenerationProfileFactory
{
    public function configured(): GenerationProfile
    {
        /** @var array<string, mixed> $configuration */
        $configuration = config('generation.quality_affecting_configuration');

        return new GenerationProfile(
            (string) config('generation.provider'),
            (string) config('generation.model'),
            (string) config('generation.contract_version'),
            (string) config('generation.prompt_version'),
            (string) config('generation.adapter_version'),
            $configuration,
        );
    }
}
