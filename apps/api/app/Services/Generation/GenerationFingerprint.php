<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Support\Evaluation\BenchmarkCanonicalJson;

final readonly class GenerationFingerprint
{
    public const SCHEME_VERSION = 1;

    public function __construct(private BenchmarkCanonicalJson $canonical) {}

    /** @param array<string, mixed> $qualityAffectingConfiguration @return array{fingerprint_scheme_version: int, generation_fingerprint: string, snapshot: array<string, mixed>} */
    public function make(string $provider, string $model, string $contractVersion, string $promptVersion, string $adapterVersion, array $qualityAffectingConfiguration): array
    {
        $snapshot = [
            'provider' => $provider,
            'model' => $model,
            'contract_version' => $contractVersion,
            'prompt_version' => $promptVersion,
            'adapter_version' => $adapterVersion,
            'quality_affecting_configuration' => $qualityAffectingConfiguration,
        ];

        return ['fingerprint_scheme_version' => self::SCHEME_VERSION, 'generation_fingerprint' => $this->canonical->digest($snapshot), 'snapshot' => $snapshot];
    }
}
