<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Exceptions\RetrievalException;
use App\Exceptions\RetrievalPlannerException;
use App\Models\DocumentChunk;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\EvidenceThresholdPolicy;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Support\Retrieval\EligibleRetrievalScope;
use App\Support\Retrieval\RetrievalPlan;
use App\Support\Retrieval\RetrievalSearchResult;
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
        $lineage = $response->json('classifier_lineage');
        $usage = $response->json('usage');
        if ($response->json('contract_version') !== 2 || ! is_array($plan) || ! is_array($lineage) || ! is_array($usage)) {
            throw new RetrievalException('The retrieval planner returned an invalid response.');
        }

        try {
            return RetrievalPlan::fromArray($plan, $question, $lineage, $usage);
        } catch (InvalidArgumentException $exception) {
            throw new RetrievalPlannerException(
                'The retrieval planner returned an invalid plan.',
                'invalid_application_plan',
                null,
                1,
                false,
                $exception,
            );
        }
    }

    public function search(
        Workspace $workspace,
        WorkspaceCorpusGeneration $corpus,
        EligibleRetrievalScope $eligible,
        string $query,
        int $candidateK,
        ?EvidenceThresholdPolicy $policy = null,
        bool $denseOnly = false,
        bool $captureDiagnostics = false,
    ): RetrievalSearchResult {
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
        $payload = [
            'contract_version' => 1,
            'workspace_id' => $workspace->public_id,
            'query' => $query,
            'embedding_profile' => $this->profile($profile),
            'embedding_profile_fingerprint' => $profile->fingerprint,
            'vector_space' => $this->vectorSpace($space, $profile),
            'workspace_corpus_generation_id' => $corpus->public_id,
            'candidate_k' => $candidateK,
            'scopes' => $scopes,
        ];
        $sparse = $denseOnly ? null : $corpus->sparseSpaceGeneration;
        if ($sparse !== null) {
            if ($policy === null) {
                throw new RetrievalException('Hybrid retrieval requires an evidence-threshold policy.');
            }
            $sparse->loadMissing('sparseEmbeddingProfile');
            $payload += [
                'sparse_embedding_profile' => $this->sparseProfile($sparse->sparseEmbeddingProfile),
                'sparse_profile_fingerprint' => $sparse->sparseEmbeddingProfile->fingerprint,
                'sparse_vector_space' => $this->sparseVectorSpace($sparse),
                'hybrid_configuration' => $this->hybridConfiguration($policy),
            ];
            $payload['vector_space']['sparse'] = $payload['sparse_vector_space'];
            $payload['candidate_k'] = $policy->dense_candidate_k;
        }
        if ($captureDiagnostics) {
            $payload['capture_diagnostics'] = true;
        }
        $response = $this->call('/api/internal/retrieval/search', 'retrieval.search', $workspace, $payload);
        $candidates = $response->json('candidates');
        $lineage = $response->json('lineage');
        $diagnostics = $response->json('diagnostics');
        $usage = $response->json('usage');
        if (! is_array($candidates) || ! is_array($lineage) || ($captureDiagnostics && ! is_array($diagnostics))) {
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
                || ! in_array($candidate['retrieval_method'], ['dense', 'sparse', 'hybrid'], true)
                || ! in_array($candidate['side'] ?? null, ['primary', 'comparison'], true)
            ) {
                throw new RetrievalException('The retriever returned a malformed candidate.');
            }
            $validated[] = $candidate;
        }

        return new RetrievalSearchResult(
            $validated,
            $lineage,
            is_array($diagnostics) ? array_values($diagnostics) : [],
            is_array($usage) ? array_values($usage) : [],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array{candidates: list<array<string, mixed>>, profile: array<string, mixed>, provider_input_tokens: int|null, provider_retry_count: int, usage: list<array<string, mixed>>}
     */
    public function rerank(
        Workspace $workspace,
        string $query,
        array $candidates,
        EvidenceThresholdPolicy $policy,
    ): array {
        if ($candidates === []) {
            throw new RetrievalException('Reranking requires at least one eligible candidate.');
        }
        $requestedKeys = collect($candidates)->map(
            fn (array $candidate): string => $candidate['side'].':'.$candidate['chunk_id']
        )->all();
        if (count($requestedKeys) !== count(array_unique($requestedKeys))) {
            throw new RetrievalException('Reranking side-qualified candidate identities must be unique.');
        }
        $profile = [
            'provider' => $policy->reranker_provider,
            'model' => $policy->reranker_model,
            'adapter_version' => $policy->reranker_adapter_version,
            'truncation' => false,
        ];
        $started = hrtime(true);
        $response = $this->call('/api/internal/retrieval/rerank', 'retrieval.rerank', $workspace, [
            'contract_version' => 1,
            'workspace_id' => $workspace->public_id,
            'query' => $query,
            'profile' => $profile,
            'candidates' => array_map(fn (array $candidate): array => [
                'chunk_id' => $candidate['chunk_id'],
                'document_id' => $candidate['document_id'],
                'document_family_id' => $candidate['document_family_id'],
                'version_position' => $candidate['version_position'],
                'side' => $candidate['side'],
                'text' => $candidate['chunk_text'],
                'fused_score' => $candidate['score'],
                'fused_rank' => $candidate['rank'],
            ], $candidates),
            'top_k' => min($policy->reranker_candidate_k, count($candidates)),
        ]);
        $returned = $response->json('candidates');
        $returnedProfile = $response->json('profile');
        $providerInputTokens = $response->json('provider_input_tokens');
        $providerRetryCount = $response->json('provider_retry_count');
        if (! is_array($returned) || ! is_array($returnedProfile)) {
            throw new RetrievalException('The reranker returned an invalid response.');
        }
        $seen = [];
        $nextRankBySide = [];
        foreach ($returned as $candidate) {
            $side = is_array($candidate) ? ($candidate['side'] ?? null) : null;
            $chunkId = is_array($candidate) ? ($candidate['chunk_id'] ?? null) : null;
            $key = is_string($side) && is_string($chunkId) ? $side.':'.$chunkId : null;
            $expectedRank = is_string($side) ? (($nextRankBySide[$side] ?? 0) + 1) : null;
            if (
                ! is_array($candidate)
                || $key === null
                || ! in_array($key, $requestedKeys, true)
                || isset($seen[$key])
                || ! is_numeric($candidate['score'] ?? null)
                || ! is_finite((float) $candidate['score'])
                || ($candidate['rank'] ?? null) !== $expectedRank
            ) {
                throw new RetrievalException('The reranker returned a malformed candidate.');
            }
            $seen[$key] = true;
            $nextRankBySide[$side] = $expectedRank;
        }

        if ($providerInputTokens !== null && (! is_int($providerInputTokens) || $providerInputTokens < 0)) {
            throw new RetrievalException('The reranker returned invalid provider usage.');
        }
        if (! is_int($providerRetryCount) || $providerRetryCount < 0) {
            throw new RetrievalException('The reranker returned invalid provider retry usage.');
        }

        return [
            'candidates' => $returned,
            'profile' => $returnedProfile,
            'provider_input_tokens' => $providerInputTokens,
            'provider_retry_count' => $providerRetryCount,
            'usage' => [[
                'stage' => 'reranking',
                'provider' => $returnedProfile['provider'] ?? $policy->reranker_provider,
                'model' => $returnedProfile['model'] ?? $policy->reranker_model,
                'execution' => 'PROVIDER_API',
                'request_count' => 1,
                'retry_count' => $providerRetryCount,
                'input_tokens' => $providerInputTokens,
                'cached_input_tokens' => null,
                'output_tokens' => null,
                'latency_ms' => (hrtime(true) - $started) / 1_000_000,
                'cost_basis' => 'UNAVAILABLE',
                'cost_usd' => null,
                'pricing_snapshot' => null,
            ]],
        ];
    }

    /**
     * @param  list<DocumentChunk>  $chunks
     * @return list<string>
     */
    public function rebuildCorpusBatch(
        Workspace $workspace,
        WorkspaceCorpusGeneration $corpus,
        array $chunks,
    ): array {
        if ($corpus->sparseSpaceGeneration === null || $corpus->rebuild_event_id === null) {
            throw new RetrievalException('A hybrid corpus rebuild requires explicit sparse and event lineage.');
        }
        $payload = $this->corpusPayload($workspace, $corpus) + [
            'rebuild_event_id' => $corpus->rebuild_event_id,
            'chunks' => array_map(static fn (DocumentChunk $chunk): array => [
                'chunk_id' => $chunk->public_id,
                'document_id' => $chunk->document->public_id,
                'text' => $chunk->text,
            ], $chunks),
        ];
        $response = $this->call(
            '/api/internal/retrieval/corpus/rebuild-batch',
            'retrieval.corpus.rebuild',
            $workspace,
            $payload,
        );
        $pointIds = $response->json('point_ids');
        if (! is_array($pointIds) || collect($pointIds)->contains(fn (mixed $id): bool => ! is_string($id))) {
            throw new RetrievalException('The corpus rebuild returned invalid point identities.');
        }

        return array_values($pointIds);
    }

    /** @return array{complete: bool, point_ids: list<string>} */
    public function verifyCorpus(
        Workspace $workspace,
        WorkspaceCorpusGeneration $corpus,
    ): array {
        $corpus->loadMissing([
            'embeddingSpaceGeneration.embeddingProfile',
            'sparseSpaceGeneration.sparseEmbeddingProfile',
            'documentChunks.document',
            'documentChunks.ingestionAttempt',
        ]);
        $points = $corpus->documentChunks->map(fn (DocumentChunk $chunk): array => [
            'chunk_id' => $chunk->public_id,
            'document_id' => $chunk->document->public_id,
            'event_id' => $corpus->rebuild_event_id
                ?? $chunk->ingestionAttempt->event_id,
        ])->values()->all();
        if ($points === []) {
            throw new RetrievalException('A corpus generation with no canonical chunks cannot be verified.');
        }
        $response = $this->call(
            '/api/internal/retrieval/corpus/verify',
            'retrieval.corpus.verify',
            $workspace,
            collect($this->corpusPayload($workspace, $corpus))->only([
                'contract_version', 'workspace_id', 'vector_space',
                'workspace_corpus_generation_id',
            ])->all() + ['points' => $points],
        );
        $complete = $response->json('complete');
        $pointIds = $response->json('point_ids');
        if (! is_bool($complete) || ! is_array($pointIds) || collect($pointIds)->contains(fn (mixed $id): bool => ! is_string($id))) {
            throw new RetrievalException('The corpus verifier returned invalid evidence.');
        }

        return ['complete' => $complete, 'point_ids' => array_values($pointIds)];
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
                if ($path === '/api/internal/retrieval/plan') {
                    $failure = $this->plannerFailure($response, $attempt);
                    $last = $failure;
                    if ($failure->systemic || in_array($failure->category, [
                        'invalid_typed_plan',
                        'query_not_preserved',
                        'provider_request_rejected',
                    ], true)) {
                        break;
                    }
                    if ($response->status() < 500 && $failure->category !== 'provider_rate_limited') {
                        break;
                    }

                    continue;
                }
                $last = new RetrievalException('The retrieval service rejected the request.');
                if ($response->status() < 500) {
                    break;
                }
            } catch (ConnectionException $exception) {
                $last = $path === '/api/internal/retrieval/plan'
                    ? new RetrievalPlannerException(
                        'The retrieval planner transport is unavailable.',
                        'transport_error',
                        null,
                        $attempt,
                        false,
                        $exception,
                    )
                    : new RetrievalException('The retrieval service is unavailable.', 0, $exception);
            }
        }

        throw $last ?? new RetrievalException('The retrieval call failed.');
    }

    private function plannerFailure(Response $response, int $attempt): RetrievalPlannerException
    {
        $detail = $response->json('detail');
        $category = is_array($detail) && is_string($detail['category'] ?? null)
            ? $detail['category']
            : 'planner_service_rejected';
        $providerStatus = is_array($detail) && is_int($detail['provider_status'] ?? null)
            ? $detail['provider_status']
            : $response->status();
        $systemic = is_array($detail) && ($detail['systemic'] ?? null) === true;
        $providerAttempts = is_array($detail) && is_int($detail['attempt_count'] ?? null)
            ? max(1, $detail['attempt_count'])
            : 1;

        return new RetrievalPlannerException(
            'The retrieval planner failed.',
            $category,
            $providerStatus,
            (($attempt - 1) * $providerAttempts) + $providerAttempts,
            $systemic,
        );
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

    /** @return array<string, mixed> */
    private function sparseProfile(SparseEmbeddingProfile $profile): array
    {
        return collect($profile->getAttributes())->only([
            'provider', 'model', 'tokenizer', 'tokenizer_revision',
            'output_representation', 'max_input_tokens', 'document_input_type',
            'query_input_type', 'model_revision', 'adapter_version',
        ])->all();
    }

    /** @return array<string, mixed> */
    private function sparseVectorSpace(SparseSpaceGeneration $space): array
    {
        return [
            'sparse_space_generation_id' => $space->public_id,
            'profile_fingerprint' => $space->sparseEmbeddingProfile->fingerprint,
            'vector_name' => $space->vector_name,
        ];
    }

    /** @return array<string, mixed> */
    private function hybridConfiguration(EvidenceThresholdPolicy $policy): array
    {
        return [
            'version' => $policy->version,
            'dense_candidate_k' => $policy->dense_candidate_k,
            'sparse_candidate_k' => $policy->sparse_candidate_k,
            'fusion_candidate_k' => $policy->fusion_candidate_k,
            'reranker_candidate_k' => $policy->reranker_candidate_k,
            'final_evidence_k' => $policy->final_evidence_k,
            'fusion_strategy' => $policy->fusion_strategy,
            'fusion_version' => $policy->fusion_version,
            'rrf_k' => $policy->rrf_k,
        ];
    }

    /** @return array<string, mixed> */
    private function corpusPayload(
        Workspace $workspace,
        WorkspaceCorpusGeneration $corpus,
    ): array {
        $corpus->loadMissing([
            'embeddingSpaceGeneration.embeddingProfile',
            'sparseSpaceGeneration.sparseEmbeddingProfile',
        ]);
        $space = $corpus->embeddingSpaceGeneration;
        $profile = $space->embeddingProfile;
        $sparse = $corpus->sparseSpaceGeneration;
        $vectorSpace = $this->vectorSpace($space, $profile);
        if ($sparse !== null) {
            $vectorSpace['sparse'] = $this->sparseVectorSpace($sparse);
        }

        return [
            'contract_version' => 1,
            'workspace_id' => $workspace->public_id,
            'embedding_profile' => $this->profile($profile),
            'sparse_embedding_profile' => $sparse === null
                ? null
                : $this->sparseProfile($sparse->sparseEmbeddingProfile),
            'vector_space' => $vectorSpace,
            'workspace_corpus_generation_id' => $corpus->public_id,
        ];
    }
}
