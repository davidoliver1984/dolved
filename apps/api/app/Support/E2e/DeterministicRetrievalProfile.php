<?php

declare(strict_types=1);

namespace App\Support\E2e;

use App\Support\Evaluation\BenchmarkCanonicalJson;
use RuntimeException;

final class DeterministicRetrievalProfile
{
    public function __construct(private BenchmarkCanonicalJson $canonical) {}

    /**
     * @return array{
     *   dense: array{profile: array<string, mixed>, fingerprint: string, space: array<string, mixed>},
     *   sparse: array{profile: array<string, mixed>, fingerprint: string, space: array<string, mixed>}
     * }
     */
    public function load(): array
    {
        $path = config('e2e.deterministic_retrieval_profile_path');
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new RuntimeException('The E2E deterministic retrieval profile fixture is unavailable.');
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)
            || ($decoded['contract_id'] ?? null) !== 'dolved-e2e-deterministic-retrieval-profile'
            || ($decoded['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('The E2E deterministic retrieval profile fixture identity is invalid.');
        }

        foreach (['dense', 'sparse'] as $capability) {
            $entry = $decoded[$capability] ?? null;
            $profile = is_array($entry) ? ($entry['profile'] ?? null) : null;
            $fingerprint = is_array($entry) ? ($entry['fingerprint'] ?? null) : null;
            $space = is_array($entry) ? ($entry['space'] ?? null) : null;
            if (! is_array($profile) || ! is_string($fingerprint) || ! is_array($space)) {
                throw new RuntimeException("The E2E {$capability} profile fixture is incomplete.");
            }
            if (! hash_equals($this->canonical->digest($profile), $fingerprint)) {
                throw new RuntimeException("The E2E {$capability} profile fingerprint is invalid.");
            }
        }

        /** @var array{dense: array{profile: array<string, mixed>, fingerprint: string, space: array<string, mixed>}, sparse: array{profile: array<string, mixed>, fingerprint: string, space: array<string, mixed>}} $decoded */
        return $decoded;
    }
}
