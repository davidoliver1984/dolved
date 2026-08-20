<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Exceptions\GenerationContextBudgetExceeded;
use App\Exceptions\GenerationException;
use App\Exceptions\GenerationProviderException;
use App\Exceptions\GenerationStreamException;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalCallSigner;
use App\Support\Generation\GenerationRequest;
use App\Telemetry\TraceContextHeaders;
use Illuminate\Support\Facades\Http;
use JsonException;

final readonly class GenerationClient
{
    public function __construct(
        private RetrievalCallSigner $signer,
        private TraceContextHeaders $traceContext,
    ) {}

    /** @return array<string, mixed> */
    public function generate(Workspace $workspace, GenerationRequest $request): array
    {
        try {
            $body = json_encode($request->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new GenerationException('The generation request could not be encoded.', 0, $exception);
        }
        $path = '/api/internal/retrieval/generation/answer';
        $headers = $this->signer->headers('POST', $path, $body, (string) $workspace->public_id, 'generation.answer', $request->requestId)
            + $this->traceContext->current();
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

    /** @param callable(array<string, mixed>): void $onCandidate @return array<string, mixed> */
    public function stream(Workspace $workspace, GenerationRequest $request, callable $onCandidate): array
    {
        try {
            $body = json_encode($request->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new GenerationStreamException('The generation stream request could not be encoded.', 0, $exception);
        }
        $path = '/api/internal/retrieval/generation/stream';
        $headers = $this->signer->headers('POST', $path, $body, (string) $workspace->public_id, 'generation.stream', $request->requestId)
            + $this->traceContext->current();
        $response = Http::timeout((float) config('retrieval.timeout_seconds'))
            ->withOptions(['stream' => true])
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post(rtrim((string) config('retrieval.ai_url'), '/').$path);
        if (! $response->successful()) {
            throw new GenerationStreamException('The generation stream service call failed.');
        }
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $expectedSequence = 1;
        $terminal = null;
        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);
            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);
                if (trim($line) === '') {
                    continue;
                }
                $event = $this->decodeStreamEvent($line, $request->requestId, $expectedSequence);
                $expectedSequence++;
                if ($event['event_type'] === 'answer_part_candidate') {
                    if ($terminal !== null || ! is_array($event['candidate'])) {
                        throw new GenerationStreamException('The generation candidate stream is invalid.');
                    }
                    $onCandidate($event['candidate']);

                    continue;
                }
                if ($terminal !== null) {
                    throw new GenerationStreamException('The generation stream has more than one terminal event.');
                }
                $terminal = $event;
            }
        }
        if (trim($buffer) !== '') {
            $event = $this->decodeStreamEvent($buffer, $request->requestId, $expectedSequence);
            $terminal = $event;
        }
        if (! is_array($terminal)) {
            throw new GenerationStreamException('The generation stream ended without a terminal event.');
        }
        if ($terminal['event_type'] === 'context_budget_exceeded' && is_array($terminal['failure'])) {
            throw new GenerationContextBudgetExceeded(
                (string) ($terminal['failure']['policy_version'] ?? ''),
                (int) ($terminal['failure']['proposed_units'] ?? 0),
                (int) ($terminal['failure']['maximum_units'] ?? 0),
            );
        }
        if ($terminal['event_type'] === 'generation_failed' && is_array($terminal['error'])) {
            throw new GenerationProviderException(
                (string) ($terminal['error']['category'] ?? 'contract_validation_failure'),
                is_string($terminal['error']['provider'] ?? null) ? $terminal['error']['provider'] : null,
                is_string($terminal['error']['model'] ?? null) ? $terminal['error']['model'] : null,
                is_int($terminal['error']['http_status'] ?? null) ? $terminal['error']['http_status'] : null,
                (int) ($terminal['error']['attempt_count'] ?? 1),
                (float) ($terminal['error']['latency_ms'] ?? 0),
            );
        }
        if ($terminal['event_type'] !== 'generation_completed' || ! is_array($terminal['result'])) {
            throw new GenerationStreamException('The generation stream terminal event is invalid.');
        }

        return $terminal['result'];
    }

    /** @return array<string, mixed> */
    private function decodeStreamEvent(string $line, string $requestId, int $expectedSequence): array
    {
        try {
            $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GenerationStreamException('The generation stream framing is invalid.', 0, $exception);
        }
        if (! is_array($event)
            || ! $this->hasExactKeys($event, ['contract_version', 'request_id', 'sequence', 'event_type', 'candidate', 'result', 'error', 'failure'])
            || ($event['contract_version'] ?? null) !== 1
            || ($event['request_id'] ?? null) !== $requestId
            || ($event['sequence'] ?? null) !== $expectedSequence) {
            throw new GenerationStreamException('The generation stream event identity or sequence is invalid.');
        }
        $shape = match ($event['event_type'] ?? null) {
            'answer_part_candidate' => is_array($event['candidate']) && $event['result'] === null && $event['error'] === null && $event['failure'] === null,
            'generation_completed' => $event['candidate'] === null && is_array($event['result']) && $event['error'] === null && $event['failure'] === null,
            'generation_failed' => $event['candidate'] === null && $event['result'] === null && is_array($event['error']) && $event['failure'] === null,
            'context_budget_exceeded' => $event['candidate'] === null && $event['result'] === null && $event['error'] === null && is_array($event['failure']),
            default => false,
        };
        if (! $shape) {
            throw new GenerationStreamException('The generation stream tagged payload is invalid.');
        }

        return $event;
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
