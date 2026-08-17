<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Exceptions\GenerationContextBudgetExceeded;
use App\Exceptions\GenerationException;
use App\Exceptions\GenerationProviderException;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalCallSigner;
use App\Support\Generation\GenerationRequest;
use Illuminate\Support\Facades\Http;
use JsonException;

final readonly class GenerationClient
{
    public function __construct(private RetrievalCallSigner $signer) {}

    /** @return array<string, mixed> */
    public function generate(Workspace $workspace, GenerationRequest $request): array
    {
        try {
            $body = json_encode($request->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new GenerationException('The generation request could not be encoded.', 0, $exception);
        }
        $path = '/api/internal/retrieval/generation/answer';
        $headers = $this->signer->headers('POST', $path, $body, (string) $workspace->public_id, 'generation.answer', $request->requestId);
        $response = Http::timeout((float) config('retrieval.timeout_seconds'))->withHeaders($headers)->withBody($body, 'application/json')->post(rtrim((string) config('retrieval.ai_url'), '/').$path);
        if (! $response->successful() || $response->json('request_id') !== $request->requestId) {
            throw new GenerationException('The generation service call failed.');
        }
        $envelope = $response->json();
        if (! is_array($envelope) || ($envelope['contract_version'] ?? null) !== 1) {
            throw new GenerationException('The generation response envelope is invalid.');
        }
        $status = $envelope['status'] ?? null;
        if ($status === 'context_budget_exceeded') {
            if (! $this->hasExactKeys($envelope, ['contract_version', 'request_id', 'status', 'failure']) || ! is_array($envelope['failure'])) {
                throw new GenerationException('The generation budget response envelope is invalid.');
            }
            throw new GenerationContextBudgetExceeded(
                (string) $response->json('failure.policy_version'),
                (int) $response->json('failure.proposed_units'),
                (int) $response->json('failure.maximum_units'),
            );
        }
        if ($status === 'provider_error') {
            if (! $this->hasExactKeys($envelope, ['contract_version', 'request_id', 'status', 'error']) || ! is_array($envelope['error'])) {
                throw new GenerationException('The generation provider response envelope is invalid.');
            }
            throw new GenerationProviderException(
                (string) ($envelope['error']['category'] ?? 'contract_validation_failure'),
                is_string($envelope['error']['provider'] ?? null) ? $envelope['error']['provider'] : null,
                is_string($envelope['error']['model'] ?? null) ? $envelope['error']['model'] : null,
                is_int($envelope['error']['http_status'] ?? null) ? $envelope['error']['http_status'] : null,
                (int) ($envelope['error']['attempt_count'] ?? 1),
                (float) ($envelope['error']['latency_ms'] ?? 0),
            );
        }
        $result = $envelope['result'] ?? null;
        if ($status !== 'completed' || ! $this->hasExactKeys($envelope, ['contract_version', 'request_id', 'status', 'result']) || ! is_array($result)) {
            throw new GenerationException('The generation response envelope is invalid.');
        }

        return $result;
    }

    /** @param array<string, mixed> $value @param list<string> $expected */
    private function hasExactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);

        return $keys === $expected;
    }
}
