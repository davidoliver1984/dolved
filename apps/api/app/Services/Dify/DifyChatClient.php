<?php

declare(strict_types=1);

namespace App\Services\Dify;

use App\Exceptions\DifyDispatchException;
use App\Support\Dify\DifyChatResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class DifyChatClient
{
    /** @param array<string, string> $inputs */
    public function send(string $query, array $inputs, string $endUser, ?string $conversationId): DifyChatResult
    {
        $apiKey = config('dify.app_api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new DifyDispatchException('configuration_missing', false);
        }

        $payload = [
            'inputs' => $inputs,
            'query' => $query,
            'response_mode' => 'blocking',
            'user' => $endUser,
            'files' => [],
        ];
        if ($conversationId !== null) {
            $payload['conversation_id'] = $conversationId;
        }

        try {
            $response = Http::baseUrl((string) config('dify.base_url'))
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) config('dify.connect_timeout_seconds'))
                ->timeout((int) config('dify.timeout_seconds'))
                ->post('/v1/chat-messages', $payload);
        } catch (ConnectionException) {
            throw new DifyDispatchException('transport_failure', true);
        }

        if (! $response->successful()) {
            throw new DifyDispatchException(
                $response->status() === 429 ? 'rate_limit' : ($response->serverError() ? 'provider_failure' : 'request_rejected'),
                $response->serverError(),
                $response->status(),
            );
        }

        $body = $response->json();
        if (! is_array($body)
            || ! is_string($body['answer'] ?? null) || trim($body['answer']) === ''
            || ! is_string($body['conversation_id'] ?? null) || ! Str::isUuid($body['conversation_id'])
            || ! is_string($body['message_id'] ?? null) || ! Str::isUuid($body['message_id'])
            || (isset($body['task_id']) && (! is_string($body['task_id']) || ! Str::isUuid($body['task_id'])))) {
            throw new DifyDispatchException('invalid_response', true, $response->status());
        }

        $metadata = is_array($body['metadata'] ?? null) ? $body['metadata'] : [];
        $resources = is_array($metadata['retriever_resources'] ?? null)
            ? array_values(array_filter($metadata['retriever_resources'], 'is_array'))
            : [];

        return new DifyChatResult(
            trim($body['answer']),
            $body['conversation_id'],
            $body['message_id'],
            is_string($body['task_id'] ?? null) ? $body['task_id'] : null,
            $resources,
            is_array($metadata['usage'] ?? null) ? $metadata['usage'] : [],
        );
    }
}
