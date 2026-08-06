<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Exceptions\IngestionWorkerAuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IngestionWorkerRequestAuthenticator
{
    public const KEY_ID_HEADER = 'X-Ingestion-Worker-Key-ID';

    public const TIMESTAMP_HEADER = 'X-Ingestion-Worker-Timestamp';

    public const EVENT_ID_HEADER = 'X-Ingestion-Worker-Event-ID';

    public const SIGNATURE_HEADER = 'X-Ingestion-Worker-Signature';

    public const PURPOSE_HEADER = 'X-Ingestion-Worker-Purpose';

    public function verify(Request $request, string $expectedPurpose): void
    {
        if ($request->getQueryString() !== null) {
            $this->fail('query_string');
        }

        $keyId = $request->header(self::KEY_ID_HEADER);
        $timestamp = $request->header(self::TIMESTAMP_HEADER);
        $eventId = $request->header(self::EVENT_ID_HEADER);
        $signature = $request->header(self::SIGNATURE_HEADER);
        $purpose = $request->header(self::PURPOSE_HEADER);

        if (
            ! is_string($keyId)
            || preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId) !== 1
        ) {
            $this->fail('key_id');
        }

        if (
            ! is_string($timestamp)
            || preg_match('/^(0|[1-9][0-9]{0,11})$/', $timestamp) !== 1
        ) {
            $this->fail('timestamp');
        }

        if (
            ! is_string($eventId)
            || ! Str::isUuid($eventId)
            || $eventId !== strtolower($eventId)
        ) {
            $this->fail('event_id');
        }

        if (
            ! is_string($signature)
            || preg_match('/^v2=[0-9a-f]{64}$/', $signature) !== 1
        ) {
            $this->fail('signature_format');
        }

        if (! is_string($purpose) || $purpose !== $expectedPurpose) {
            $this->fail('purpose');
        }

        $routeEventId = $request->route('eventId');

        if (! is_string($routeEventId) || $routeEventId !== $eventId) {
            $this->fail('route_event_id');
        }

        $skew = max(
            1,
            (int) config('ingestion.worker_auth.max_clock_skew_seconds'),
        );

        if (abs(now()->timestamp - (int) $timestamp) > $skew) {
            $this->fail('timestamp_window');
        }

        $keys = config('ingestion.worker_auth.keys');

        if (
            ! is_array($keys)
            || ! array_key_exists($keyId, $keys)
            || ! is_string($keys[$keyId])
        ) {
            $this->fail('unknown_key');
        }

        $secret = base64_decode($keys[$keyId], true);

        if (
            $secret === false
            || strlen($secret) < 32
            || base64_encode($secret) !== $keys[$keyId]
        ) {
            $this->fail('invalid_key');
        }

        $requestPath = explode('?', $request->getRequestUri(), 2)[0];
        $bodyDigest = hash('sha256', $request->getContent());
        $canonical = implode("\n", [
            $timestamp,
            strtoupper($request->getMethod()),
            $requestPath,
            $bodyDigest,
            $eventId,
            $purpose,
        ]);
        $expected = hash_hmac('sha256', $canonical, $secret);
        $provided = substr($signature, 3);

        if (! hash_equals($expected, $provided)) {
            $this->fail('signature_mismatch');
        }
    }

    private function fail(string $reason): never
    {
        throw new IngestionWorkerAuthenticationException($reason);
    }
}
