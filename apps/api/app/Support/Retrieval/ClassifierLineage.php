<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use InvalidArgumentException;

final readonly class ClassifierLineage
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $contractSchemaVersion,
        public string $promptVersion,
        public string $adapterVersion,
        public string $fingerprint,
    ) {}

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $keys = ['provider', 'model', 'contract_schema_version', 'prompt_version', 'adapter_version'];
        $parts = [];
        foreach ($keys as $key) {
            if (! is_string($value[$key] ?? null) || trim($value[$key]) === '') {
                throw new InvalidArgumentException('The planner classifier lineage is incomplete.');
            }
            $parts[$key] = $value[$key];
        }
        $fingerprint = $value['fingerprint'] ?? null;
        ksort($parts);
        $expected = hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (! is_string($fingerprint) || ! hash_equals($expected, $fingerprint)) {
            throw new InvalidArgumentException('The planner classifier lineage fingerprint is invalid.');
        }

        return new self(
            $parts['provider'],
            $parts['model'],
            $parts['contract_schema_version'],
            $parts['prompt_version'],
            $parts['adapter_version'],
            $fingerprint,
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'contract_schema_version' => $this->contractSchemaVersion,
            'prompt_version' => $this->promptVersion,
            'adapter_version' => $this->adapterVersion,
            'fingerprint' => $this->fingerprint,
        ];
    }
}
