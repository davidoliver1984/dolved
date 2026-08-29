<?php

declare(strict_types=1);

namespace App\Support\Ingestion;

use App\Models\EmbeddingSpaceGeneration;
use App\Models\WorkspaceCorpusGeneration;

final class MaterialisationPipelineIdentity
{
    /** @return array{fingerprint: string, components: array<string, mixed>} */
    public function for(
        EmbeddingSpaceGeneration $embedding,
        WorkspaceCorpusGeneration $corpus,
    ): array {
        $embedding->loadMissing('embeddingProfile');
        $corpus->loadMissing('sparseSpaceGeneration.sparseEmbeddingProfile');
        $sparse = $corpus->sparseSpaceGeneration;
        $configured = config('ingestion.orchestration.materialisation_pipeline', []);

        $components = [
            'worker' => [
                'purpose' => 'document.ingestion',
                'protocol_family' => 'ingestion-worker-hmac',
                'contract_version' => (string) ($configured['worker_contract_version'] ?? 'document-ingestion-requested-v1'),
            ],
            'extraction' => [
                'artifact_schema_version' => (string) ($configured['artifact_schema_version'] ?? 'document-extraction-artifact-v1'),
                'source_extractor_identity' => (string) ($configured['source_extractor_identity'] ?? 'source-extractor-v1'),
                'normaliser_identity' => (string) ($configured['normaliser_identity'] ?? 'structured-normaliser-v1'),
                'projection_schema_version' => (string) ($configured['projection_schema_version'] ?? 'structured-projection-v1'),
                'digest_algorithm_version' => (string) ($configured['digest_algorithm_version'] ?? 'canonical-json-sha256-v1'),
            ],
            'chunking' => [
                'strategy_name' => (string) ($configured['chunk_strategy_name'] ?? 'baseline'),
                'strategy_version' => (string) ($configured['chunk_strategy_version'] ?? 'v1'),
                'configuration_fingerprint' => (string) ($configured['chunk_configuration_fingerprint'] ?? hash('sha256', 'baseline-v1')),
            ],
            'dense' => [
                'embedding_profile_fingerprint' => $embedding->embeddingProfile->fingerprint,
                'embedding_space_generation_id' => $embedding->public_id,
                'collection_name' => $embedding->collection_name,
                'vector_name' => $embedding->vector_name,
                'dimensions' => $embedding->dimensions,
                'distance' => $embedding->distance,
            ],
            'sparse' => $sparse === null ? null : [
                'sparse_profile_fingerprint' => $sparse->sparseEmbeddingProfile->fingerprint,
                'sparse_space_generation_id' => $sparse->public_id,
                'vector_name' => $sparse->vector_name,
            ],
            'workspace_corpus_generation_id' => $corpus->public_id,
        ];
        $canonical = $this->canonical($components);

        return [
            'fingerprint' => hash('sha256', json_encode(
                $canonical,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            )),
            'components' => $canonical,
        ];
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function canonical(array $value): array
    {
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(fn (mixed $nested): mixed => is_array($nested) ? $this->canonical($nested) : $nested, $item)
                    : $this->canonical($item);
            }
        }

        return $value;
    }
}
