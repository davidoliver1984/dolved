<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\ContentCloneVectorGateway;
use App\Exceptions\IngestionAttemptException;
use App\Models\DocumentContentCloneManifest;
use App\Models\DocumentContentCloneOperation;
use App\Services\Retrieval\RetrievalCallSigner;
use App\Telemetry\TraceContextHeaders;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final readonly class AiContentCloneVectorGateway implements ContentCloneVectorGateway
{
    public function __construct(
        private RetrievalCallSigner $signer,
        private TraceContextHeaders $traceContext,
    ) {}

    public function clone(
        DocumentContentCloneOperation $operation,
        DocumentContentCloneManifest $manifest,
        string $leaseToken,
    ): array {
        $operation->loadMissing([
            'sourceDocument', 'targetDocument',
            'sourceAttempt.embeddingSpaceGeneration.embeddingProfile',
            'sourceAttempt.workspaceCorpusGeneration.sparseSpaceGeneration.sparseEmbeddingProfile',
            'targetAttempt', 'workspace',
        ]);
        $attempt = $operation->targetAttempt;
        $requestId = (string) Str::uuid();
        $path = '/api/internal/content-clone/vector-copy';
        $payload = [
            'contract_version' => 1,
            'request_id' => $requestId,
            'operation_id' => $operation->public_id,
            'workspace_id' => $operation->workspace->public_id,
            'source_document_id' => $operation->sourceDocument->public_id,
            'target_document_id' => $operation->targetDocument->public_id,
            'source_event_id' => $operation->sourceAttempt->event_id,
            'target_event_id' => $attempt->event_id,
            'lease_generation' => $attempt->lease_generation,
            'lease_token' => $leaseToken,
            'manifest' => [
                'bucket' => (string) config('filesystems.disks.'.config('ingestion.orchestration.content_clone_manifest_disk').'.bucket'),
                'object_key' => $manifest->object_key,
                'checksum_sha256' => $manifest->checksum_sha256,
                'entry_count' => $manifest->entry_count,
                'schema_version' => $manifest->schema_version,
            ],
            'pipeline_fingerprint' => $operation->materialisation_pipeline_fingerprint,
            'pipeline_components' => $operation->materialisation_pipeline_components,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->signer->headers('POST', $path, $body, $payload['workspace_id'], 'content.clone', $requestId)
            + $this->traceContext->current();
        $response = Http::timeout((float) config('retrieval.timeout_seconds'))
            ->withHeaders($headers)->withBody($body, 'application/json')
            ->post(rtrim((string) config('retrieval.ai_url'), '/').$path);
        if (! $response->successful()) {
            throw IngestionAttemptException::invalid('clone_vector_copy_failed', 'The vector clone did not complete.', 503);
        }
        $result = $response->json();
        if (
            ! is_array($result)
            || ($result['request_id'] ?? null) !== $requestId
            || ! is_bool($result['complete'] ?? null)
            || ! is_int($result['point_count'] ?? null)
            || ! is_string($result['point_manifest_digest'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/', $result['point_manifest_digest']) !== 1
        ) {
            throw IngestionAttemptException::invalid('clone_vector_report_invalid', 'The vector clone returned invalid evidence.', 502);
        }

        return [
            'complete' => $result['complete'],
            'point_count' => $result['point_count'],
            'point_manifest_digest' => $result['point_manifest_digest'],
        ];
    }

    public function cleanup(DocumentContentCloneOperation $operation): bool
    {
        $operation->loadMissing(['targetDocument', 'targetAttempt', 'workspace']);
        $requestId = (string) Str::uuid();
        $path = '/api/internal/content-clone/vector-cleanup';
        $payload = [
            'contract_version' => 1,
            'request_id' => $requestId,
            'operation_id' => $operation->public_id,
            'workspace_id' => $operation->workspace->public_id,
            'target_document_id' => $operation->targetDocument->public_id,
            'target_event_id' => $operation->targetAttempt->event_id,
            'pipeline_components' => $operation->materialisation_pipeline_components,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->signer->headers('POST', $path, $body, $payload['workspace_id'], 'content.clone.cleanup', $requestId)
            + $this->traceContext->current();
        $response = Http::timeout((float) config('retrieval.timeout_seconds'))
            ->withHeaders($headers)->withBody($body, 'application/json')
            ->post(rtrim((string) config('retrieval.ai_url'), '/').$path);

        return $response->successful()
            && $response->json('request_id') === $requestId
            && $response->json('absent') === true;
    }
}
