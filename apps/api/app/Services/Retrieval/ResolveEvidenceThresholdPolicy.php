<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Exceptions\RetrievalException;
use App\Models\EvidenceThresholdPolicy;
use App\Models\WorkspaceCorpusGeneration;

final class ResolveEvidenceThresholdPolicy
{
    public function handle(WorkspaceCorpusGeneration $corpus): EvidenceThresholdPolicy
    {
        $corpus->loadMissing([
            'embeddingSpaceGeneration.embeddingProfile',
            'sparseSpaceGeneration.sparseEmbeddingProfile',
        ]);
        $sparse = $corpus->sparseSpaceGeneration;
        if ($sparse === null) {
            throw new RetrievalException('A dense-only corpus does not require an evidence-threshold policy.');
        }
        $policy = EvidenceThresholdPolicy::query()
            ->where('status', EvidenceThresholdPolicyStatus::Active->value)
            ->where(
                'embedding_profile_fingerprint',
                $corpus->embeddingSpaceGeneration->embeddingProfile->fingerprint,
            )
            ->where(
                'sparse_profile_fingerprint',
                $sparse->sparseEmbeddingProfile->fingerprint,
            )
            ->first();
        if (
            $policy === null
            || $policy->embedding_profile_fingerprint !== $corpus->embeddingSpaceGeneration->embeddingProfile->fingerprint
            || $policy->sparse_profile_fingerprint !== $sparse->sparseEmbeddingProfile->fingerprint
        ) {
            throw new RetrievalException('No compatible active evidence-threshold policy is available.');
        }

        return $policy;
    }

    /** @param array<string, mixed> $lineage */
    public function assertSearchLineage(EvidenceThresholdPolicy $policy, array $lineage): void
    {
        $expected = [
            'embedding_profile_fingerprint' => $policy->embedding_profile_fingerprint,
            'sparse_profile_fingerprint' => $policy->sparse_profile_fingerprint,
            'fusion_strategy' => $policy->fusion_strategy,
            'fusion_version' => $policy->fusion_version,
            'rrf_k' => $policy->rrf_k,
            'configuration_version' => $policy->version,
        ];
        foreach ($expected as $field => $value) {
            if (($lineage[$field] ?? null) !== $value) {
                throw new RetrievalException('Hybrid retrieval lineage does not match the active policy.');
            }
        }
    }

    /** @param array<string, mixed> $profile */
    public function assertRerankerLineage(EvidenceThresholdPolicy $policy, array $profile): void
    {
        if (
            ($profile['provider'] ?? null) !== $policy->reranker_provider
            || ($profile['model'] ?? null) !== $policy->reranker_model
            || ($profile['adapter_version'] ?? null) !== $policy->reranker_adapter_version
            || ($profile['truncation'] ?? null) !== false
        ) {
            throw new RetrievalException('Reranker lineage does not match the active policy.');
        }
    }
}
