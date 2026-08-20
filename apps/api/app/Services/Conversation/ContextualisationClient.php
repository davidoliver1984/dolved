<?php

declare(strict_types=1);

namespace App\Services\Conversation;

use App\Exceptions\GenerationException;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalCallSigner;
use App\Support\Conversation\ContextualisationRequest;
use App\Support\Conversation\ContextualisationResult;
use App\Telemetry\TraceContextHeaders;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;

final readonly class ContextualisationClient
{
    public function __construct(
        private RetrievalCallSigner $signer,
        private TraceContextHeaders $traceContext,
    ) {}

    public function contextualise(Workspace $workspace, ContextualisationRequest $request): ContextualisationResult
    {
        try {
            $body = json_encode($request->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new GenerationException('The contextualisation request could not be encoded.', 0, $exception);
        }
        $path = '/api/internal/retrieval/conversation/contextualize';
        $headers = $this->signer->headers('POST', $path, $body, (string) $workspace->public_id, 'conversation.contextualize', $request->requestId)
            + $this->traceContext->current();
        $response = Http::timeout((float) config('retrieval.timeout_seconds'))
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post(rtrim((string) config('retrieval.ai_url'), '/').$path);
        if (! $response->successful() || $response->json('request_id') !== $request->requestId || $response->json('contract_version') !== 1) {
            throw new GenerationException('The contextualisation service call failed.');
        }
        $result = $response->json('result');
        if (! is_array($result)) {
            throw new GenerationException('The contextualisation response is invalid.');
        }

        try {
            return ContextualisationResult::fromArray($result);
        } catch (InvalidArgumentException $exception) {
            throw new GenerationException('The contextualisation response is invalid.', 0, $exception);
        }
    }
}
