<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Exceptions\DocumentGovernanceException;
use Throwable;

final class FamilyDeletionConfirmationDigest
{
    /** @param array<string, mixed> $state */
    public function issue(int $actorId, array $state, string $stateDigest): string
    {
        $payload = $this->encode(json_encode([
            'actor_id' => $actorId,
            'family_id' => $state['family']['id'],
            'state_digest' => $stateDigest,
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $payload.'.'.hash_hmac('sha256', $payload, $this->key());
    }

    /** @return array{actor_id: int, family_id: int, state_digest: string, expires_at: int} */
    public function verify(string $token): array
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if (! is_string($payload) || ! is_string($signature)
            || ! hash_equals(hash_hmac('sha256', $payload, $this->key()), $signature)) {
            throw new DocumentGovernanceException('The family-deletion confirmation digest is invalid.');
        }
        try {
            $decoded = json_decode($this->decode($payload), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new DocumentGovernanceException('The family-deletion confirmation digest is invalid.');
        }
        if (! is_array($decoded) || ($decoded['expires_at'] ?? 0) < now()->getTimestamp()) {
            throw new DocumentGovernanceException('The family-deletion confirmation digest has expired.');
        }

        return $decoded;
    }

    private function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new DocumentGovernanceException('The application key is unavailable.');
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) {
            throw new DocumentGovernanceException('The family-deletion confirmation digest is invalid.');
        }

        return $decoded;
    }
}
