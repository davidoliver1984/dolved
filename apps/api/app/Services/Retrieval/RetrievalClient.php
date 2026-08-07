<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Exceptions\RetrievalException;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Support\Retrieval\EligibleRetrievalScope;
use App\Support\Retrieval\RetrievalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class RetrievalClient
{
    public function __construct(private RetrievalCallSigner $signer) {}

    public function plan(
        Workspace $workspace,
        string $question,
        CarbonImmutable $evaluatedAt,
    ): RetrievalPlan {
        $response = $this->call('/api/internal/retrieval/plan', 'retrieval.plan', $workspace, [
            'contract_version' => 1,
            'workspace_id' => $workspace->public_id,
            'question' => $question,
            'evaluated_at' => $evaluatedAt->toIso8601String(),
        ]);
        $plan = $response->json('plan');
        if (! is_array($plan)) {
            throw new RetrievalException('The retrieval planner returned an invalid response.');
        }

        try {
            return RetrievalPlan::fromArray($plan, $question);
        } catch (InvalidArgumentException $exception) {
            throw new RetrievalException(
                'The retrieval planner returned an invalid plan.',
                0,
                $exception,
            );
        }
    }

    /** @return list<array<string, mixed>> */
    public function search(
        Workspace $workspace,
        WorkspaceCorpusGeneration $corpus,
        EligibleRetrievalScope $eligible,
        string $query,
        int $candidateK,
    ): array {
        $space = $corpus->embeddingSpaceGeneration;
        $profile = $space->embeddingProfile;
        $scopes = [];
        foreach ($eligible->documentPublicIdsBySide as $side => $documentIds) {
            if (count($documentIds) > (int) config('retrieval.max_eligible_documents')) {
                throw new RetrievalException('The eligible retrieval scope exceeds the configured bound.');
            }
            $scopes[] = [
                'side' => $side,
                'eligible_document_ids' => array_values($documentIds),
            ];
        }
        $response = $this->call('/api/internal/retrieval/search', 'retrieval.search', $workspace, [
            'contract_version' => 1,
            'workspace_id' => $workspace->public_id,
            'query' => $query,
            'embedding_profile' => $this->profile($profile),
            'embedding_profile_fingerprint' => $profile->fingerprint,
            'vector_space' => $this->vectorSpace($space, $profile),
            'workspace_corpus_generation_id' => $corpus->public_id,
            'candidate_k' => $candidateK,
            'scopes' => $scopes,
        ]);
        $candidates = $response->json('candidates');
        if (! is_array($candidates)) {
            throw new RetrievalException('The retriever returned an invalid response.');
        }
        $validated = [];
        foreach ($candidates as $candidate) {
            if (
                ! is_array($candidate)
                || ! is_string($candidate['chunk_id'] ?? null)
                || ! is_string($candidate['document_id'] ?? null)
                || ! is_string($candidate['workspace_corpus_generation_id'] ?? null)
                || ! is_string($candidate['embedding_space_generation_id'] ?? null)
                || ! is_numeric($candidate['score'] ?? null)
                || ! is_int($candidate['rank'] ?? null)
                || ! is_string($candidate['retrieval_method'] ?? null)
                || ! in_array($candidate['side'] ?? null, ['primary', 'comparison'], true)
            ) {
                throw new RetrievalException('The retriever returned a malformed candidate.');
            }
            $validated[] = $candidate;
        }

        return $validated;
    }

    /** @param array<string, mixed> $payload */
    private function call(
        string $path,
        string $purpose,
        Workspace $workspace,
        array $payload,
    ): Response {
        $baseUrl = rtrim((string) config('retrieval.ai_url'), '/');
        if (app()->environment('production') && ! str_starts_with($baseUrl, 'https://')) {
            throw new RetrievalException('Production retrieval calls require authenticated TLS.');
        }
        $attempts = max(1, (int) config('retrieval.max_attempts'));
        $last = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $requestId = (string) Str::uuid();
            $attemptPayload = ['request_id' => $requestId] + $payload;
            try {
                $body = json_encode(
                    $attemptPayload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );
            } catch (JsonException $exception) {
                throw new RetrievalException('The retrieval request could not be encoded.', 0, $exception);
            }
            $headers = $this->signer->headers(
                'POST',
                $path,
                $body,
                (string) $workspace->public_id,
                $purpose,
                $requestId,
            );
            try {
                $response = Http::timeout((float) config('retrieval.timeout_seconds'))
                    ->withHeaders($headers)
                    ->withBody($body, 'application/json')
                    ->post($baseUrl.$path);
                if ($response->successful()) {
                    if ($response->json('request_id') !== $requestId) {
                        throw new RetrievalException('The retrieval response identity does not match the request.');
                    }

                    return $response;
                }
                $last = new RetrievalException('The retrieval service rejected the request.');
                if ($response->status() < 500) {
                    break;
                }
            } catch (ConnectionException $exception) {
                $last = new RetrievalException('The retrieval service is unavailable.', 0, $exception);
            }
        }

        throw $last ?? new RetrievalException('The retrieval call failed.');
    }

    /** @return array<string, mixed> */
    private function profile(EmbeddingProfile $profile): array
    {
        return [
            'provider' => $profile->provider,
            'model' => $profile->model,
            'dimensions' => $profile->dimensions,
            'output_dtype' => $profile->output_dtype,
            'document_input_type' => $profile->document_input_type,
            'query_input_type' => $profile->query_input_type,
            'normalisation' => $profile->normalisation,
            'truncation' => $profile->truncation,
            'model_revision' => $profile->model_revision,
            'adapter_version' => $profile->adapter_version,
        ];
    }

    /** @return array<string, mixed> */
    private function vectorSpace(EmbeddingSpaceGeneration $space, EmbeddingProfile $profile): array
    {
        return [
            'collection_name' => $space->collection_name,
            'embedding_space_generation_id' => $space->public_id,
            'profile_fingerprint' => $profile->fingerprint,
            'vector_name' => $space->vector_name,
            'dimensions' => $space->dimensions,
            'distance' => $space->distance,
        ];
    }
}
