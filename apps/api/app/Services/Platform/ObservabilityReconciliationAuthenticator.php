<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Exceptions\ObservabilityReconciliationException;
use App\Models\ObservabilityReconciliationNonce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ObservabilityReconciliationAuthenticator
{
    public const PREFIX = 'X-Observability-Reconciliation-';

    public function verify(Request $request, string $expectedPurpose): void
    {
        if (strlen($request->getContent()) > (int) config('platform.reconciliation.max_body_bytes', 16384)) {
            $this->fail('body_size');
        }
        $values = [];
        foreach (['Key-ID', 'Key-Version', 'Timestamp', 'Request-ID', 'Purpose', 'Environment', 'Policy-Version', 'Setting-Key', 'Target', 'Deployment-Attempt-ID', 'Plan-ID', 'Correlation-ID', 'Signature'] as $name) {
            $value = $request->header(self::PREFIX.$name);
            if (! is_string($value) || $value === '') {
                $this->fail('header_'.strtolower(str_replace('-', '_', $name)));
            }
            $values[$name] = $value;
        }
        if ($values['Purpose'] !== $expectedPurpose
            || $values['Environment'] !== (string) config('platform.reconciliation.environment')
            || ! Str::isUuid($values['Request-ID'])
            || ! Str::isUuid($values['Deployment-Attempt-ID'])
            || ! Str::isUuid($values['Plan-ID'])
            || ! Str::isUuid($values['Correlation-ID'])
            || preg_match('/^[1-9][0-9]*$/', $values['Policy-Version']) !== 1
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $values['Setting-Key']) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $values['Target']) !== 1
            || preg_match('/^[0-9]{1,12}$/', $values['Timestamp']) !== 1
            || preg_match('/^or1=[0-9a-f]{64}$/', $values['Signature']) !== 1) {
            $this->fail('shape');
        }
        if (abs(now()->timestamp - (int) $values['Timestamp']) > (int) config('platform.reconciliation.max_clock_skew_seconds', 300)) {
            $this->fail('timestamp');
        }
        $credential = collect(config('platform.reconciliation.credentials', []))->first(
            fn (mixed $candidate): bool => is_array($candidate)
                && hash_equals((string) ($candidate['key_id'] ?? ''), $values['Key-ID'])
                && hash_equals((string) ($candidate['version'] ?? ''), $values['Key-Version'])
        );
        if (! is_array($credential) || ($credential['revoked'] ?? true) === true) {
            $this->fail('credential');
        }
        $secret = base64_decode((string) ($credential['secret'] ?? ''), true);
        if ($secret === false || strlen($secret) < 32) {
            $this->fail('credential');
        }
        $path = explode('?', $request->getRequestUri(), 2)[0];
        $canonical = implode("\n", [
            'or1', $expectedPurpose, strtoupper($request->method()), $path,
            $values['Key-ID'], $values['Key-Version'], $values['Environment'],
            $values['Policy-Version'], $values['Setting-Key'], $values['Target'],
            strtolower($values['Deployment-Attempt-ID']), strtolower($values['Plan-ID']),
            hash('sha256', $request->getContent()), $values['Timestamp'],
            strtolower($values['Request-ID']), strtolower($values['Correlation-ID']),
        ]);
        if (! hash_equals(hash_hmac('sha256', $canonical, $secret), substr($values['Signature'], 4))) {
            $this->fail('signature');
        }

        try {
            DB::transaction(function () use ($values): void {
                ObservabilityReconciliationNonce::query()->where('expires_at', '<', now())->delete();
                ObservabilityReconciliationNonce::query()->create([
                    'request_id' => strtolower($values['Request-ID']),
                    'expires_at' => now()->addSeconds((int) config('platform.reconciliation.nonce_ttl_seconds', 900)),
                ]);
            });
        } catch (\Throwable) {
            $this->fail('replay');
        }

        $request->attributes->set('observability_reconciliation', $values);
    }

    private function fail(string $reason): never
    {
        throw new ObservabilityReconciliationException($reason);
    }
}
