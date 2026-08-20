<?php

declare(strict_types=1);

namespace App\Services\Platform;

use RuntimeException;

final class PlatformAdministrationCredentialRegistry
{
    /** @return array{key_id: string, version: string} */
    public function authenticate(string $keyId, string $secret): array
    {
        foreach (config('platform.administration_credentials', []) as $credential) {
            if (! is_array($credential) || ! hash_equals((string) ($credential['key_id'] ?? ''), $keyId)) {
                continue;
            }
            if (($credential['revoked'] ?? true) === true) {
                throw new RuntimeException('The platform administration credential is revoked.');
            }
            $configured = base64_decode((string) ($credential['secret'] ?? ''), true);
            if ($configured === false || $configured === '' || ! hash_equals($configured, $secret)) {
                throw new RuntimeException('The platform administration credential is invalid.');
            }

            return ['key_id' => $keyId, 'version' => (string) ($credential['version'] ?? 'v1')];
        }

        throw new RuntimeException('The platform administration credential is invalid.');
    }
}
