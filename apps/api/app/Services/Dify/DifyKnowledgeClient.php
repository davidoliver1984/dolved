<?php

declare(strict_types=1);

namespace App\Services\Dify;

use App\Exceptions\DifyKnowledgeException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class DifyKnowledgeClient
{
    /** @param resource $stream
     * @return array{document_id: string, batch_id: string}
     */
    public function createDocument(string $datasetId, string $filename, $stream): array
    {
        $response = $this->request()
            ->attach('data', json_encode($this->documentConfiguration(), JSON_THROW_ON_ERROR))
            ->attach('file', $stream, $filename)
            ->post($this->url("/v1/datasets/{$datasetId}/document/create-by-file"));
        $this->assertSuccessful($response);
        $documentId = $response->json('document.id');
        $batchId = $response->json('batch');
        if (! is_string($documentId) || ! Str::isUuid($documentId)
            || ! is_string($batchId) || ! Str::isUuid($batchId)) {
            throw new DifyKnowledgeException('invalid_create_response');
        }

        return ['document_id' => $documentId, 'batch_id' => $batchId];
    }

    /** @param resource $stream
     * @return array{document_id: string, batch_id: string}
     */
    public function updateDocument(string $datasetId, string $documentId, string $filename, $stream): array
    {
        $response = $this->request()
            ->attach('data', json_encode($this->documentConfiguration(), JSON_THROW_ON_ERROR))
            ->attach('file', $stream, $filename)
            ->patch($this->url("/v1/datasets/{$datasetId}/documents/{$documentId}"));
        $this->assertSuccessful($response);
        $updatedDocumentId = $response->json('document.id');
        $batchId = $response->json('batch');
        if (! is_string($updatedDocumentId) || ! Str::isUuid($updatedDocumentId)
            || ! hash_equals($documentId, $updatedDocumentId)
            || ! is_string($batchId) || ! Str::isUuid($batchId)) {
            throw new DifyKnowledgeException('invalid_update_response');
        }

        return ['document_id' => $updatedDocumentId, 'batch_id' => $batchId];
    }

    /** @return array<int, array<string, mixed>> */
    public function indexingStatus(string $datasetId, string $batchId): array
    {
        $response = $this->request()->get($this->url("/v1/datasets/{$datasetId}/documents/{$batchId}/indexing-status"));
        $this->assertSuccessful($response);
        $data = $response->json('data');
        if (! is_array($data)) {
            throw new DifyKnowledgeException('invalid_indexing_response');
        }

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public function findDocuments(string $datasetId, string $exactName): array
    {
        $response = $this->request()->get($this->url("/v1/datasets/{$datasetId}/documents"), [
            'keyword' => $exactName,
            'limit' => 100,
        ]);
        $this->assertSuccessful($response);
        $data = $response->json('data');
        if (! is_array($data)) {
            throw new DifyKnowledgeException('invalid_document_list_response');
        }

        return array_values(array_filter($data, fn (mixed $item): bool => is_array($item)
            && hash_equals($exactName, (string) ($item['name'] ?? ''))));
    }

    public function setScopeMetadata(
        string $datasetId,
        string $documentId,
        string $metadataId,
        string $scopeKey,
    ): void {
        $response = $this->request()->post($this->url("/v1/datasets/{$datasetId}/documents/metadata"), [
            'operation_data' => [[
                'document_id' => $documentId,
                'metadata_list' => [[
                    'id' => $metadataId,
                    'name' => 'scope_key',
                    'value' => $scopeKey,
                ]],
                'partial_update' => false,
            ]],
        ]);
        $this->assertSuccessful($response);
    }

    public function changeStatus(string $datasetId, string $documentId, string $action): void
    {
        if (! in_array($action, ['enable', 'disable'], true)) {
            throw new DifyKnowledgeException('invalid_status_action');
        }
        $response = $this->request()->patch(
            $this->url("/v1/datasets/{$datasetId}/documents/status/{$action}"),
            ['document_ids' => [$documentId]],
        );
        $this->assertSuccessful($response);
    }

    public function deleteDocument(string $datasetId, string $documentId): void
    {
        $response = $this->request()->delete($this->url("/v1/datasets/{$datasetId}/documents/{$documentId}"));
        if ($response->status() === 404) {
            return;
        }
        $this->assertSuccessful($response);
    }

    /** @return array<string, mixed> */
    private function documentConfiguration(): array
    {
        return [
            'indexing_technique' => 'high_quality',
            'doc_form' => 'text_model',
            'doc_language' => 'English',
            'process_rule' => [
                'mode' => 'custom',
                'rules' => [
                    'pre_processing_rules' => [],
                    'segmentation' => [
                        'separator' => (string) config('dify.document_separator'),
                        'max_tokens' => (int) config('dify.document_chunk_size'),
                        'chunk_overlap' => (int) config('dify.document_chunk_overlap'),
                    ],
                ],
            ],
        ];
    }

    private function request(): PendingRequest
    {
        $key = config('dify.knowledge_api_key');
        if (! is_string($key) || $key === '') {
            throw new DifyKnowledgeException('missing_knowledge_api_key');
        }

        return Http::withToken($key)
            ->acceptJson()
            ->connectTimeout((int) config('dify.connect_timeout_seconds'))
            ->timeout((int) config('dify.timeout_seconds'));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('dify.base_url'), '/').$path;
    }

    private function assertSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new DifyKnowledgeException('http_'.$response->status());
    }
}
