<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Exceptions\RetrievalException;
use Illuminate\Support\Str;

final class RetrievalCallSigner
{
    /** @return array<string, string> */
    public function headers(
        string $method,
        string $path,
        string $body,
        string $workspaceId,
        string $purpose,
        string $requestId,
        ?int $timestamp = null,
    ): array {
        $keyId = config('retrieval.caller.key_id');
        $encodedSecret = config('retrieval.caller.secret');
        if (! is_string($keyId) || preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId) !== 1) {
            throw new RetrievalException('The retrieval caller Key ID is invalid.');
        }
        if (! is_string($encodedSecret)) {
            throw new RetrievalException('The retrieval caller secret is unavailable.');
        }
        $secret = base64_decode($encodedSecret, true);
        if ($secret === false || strlen($secret) < 32 || base64_encode($secret) !== $encodedSecret) {
            throw new RetrievalException('The retrieval caller secret is invalid.');
        }
        if (! Str::isUuid($workspaceId) || ! Str::isUuid($requestId)) {
            throw new RetrievalException('Retrieval call identities must be UUIDs.');
        }
        $time = (string) ($timestamp ?? now()->timestamp);
        $canonical = implode("\n", [
            $time,
            strtoupper($method),
            $path,
            hash('sha256', $body),
            strtolower($workspaceId),
            $purpose,
            strtolower($requestId),
        ]);

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Retrieval-Caller-Key-ID' => $keyId,
            'X-Retrieval-Caller-Timestamp' => $time,
            'X-Retrieval-Caller-Workspace-ID' => strtolower($workspaceId),
            'X-Retrieval-Caller-Request-ID' => strtolower($requestId),
            'X-Retrieval-Caller-Purpose' => $purpose,
            'X-Retrieval-Caller-Signature' => 'rc1='.hash_hmac('sha256', $canonical, $secret),
        ];
    }
}
