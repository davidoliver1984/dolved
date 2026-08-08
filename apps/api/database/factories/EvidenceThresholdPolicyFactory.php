<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvidenceThresholdPolicy> */
class EvidenceThresholdPolicyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'version' => 'test-'.fake()->unique()->uuid(),
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'status' => EvidenceThresholdPolicyStatus::Calibrating,
            'reranker_provider' => 'deterministic-fake',
            'reranker_model' => 'deterministic-reranker',
            'reranker_adapter_version' => '1',
            'embedding_profile_fingerprint' => hash('sha256', 'dense-test'),
            'sparse_profile_fingerprint' => hash('sha256', 'sparse-test'),
            'fusion_strategy' => 'rrf',
            'fusion_version' => '1',
            'rrf_k' => 60,
            'dense_candidate_k' => 40,
            'sparse_candidate_k' => 40,
            'fusion_candidate_k' => 15,
            'reranker_candidate_k' => 15,
            'evidence_threshold' => 0.8,
            'final_evidence_k' => 5,
            'calibration_corpus_version' => 'v3-calibration',
            'calibration_corpus_digest' => hash('sha256', 'calibration-test'),
            'activated_at' => null,
            'retired_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => EvidenceThresholdPolicyStatus::Active,
            'activated_at' => now(),
            'retired_at' => null,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (): array => [
            'status' => EvidenceThresholdPolicyStatus::Retired,
            'activated_at' => now()->subDay(),
            'retired_at' => now(),
        ]);
    }
}
