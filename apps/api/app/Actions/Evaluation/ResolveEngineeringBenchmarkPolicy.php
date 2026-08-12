<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use App\Models\WorkspaceCorpusGeneration;
use App\Support\Evaluation\BenchmarkCanonicalJson;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkState;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ResolveEngineeringBenchmarkPolicy
{
    public function __construct(
        private EngineeringBenchmarkState $states,
        private BenchmarkCanonicalJson $canonical,
    ) {}

    public function handle(WorkspaceCorpusGeneration $corpus): EvidenceThresholdPolicy
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Engineering benchmark policy resolution is restricted to local/testing environments.');
        }
        $state = $this->states->read();
        if (($state['status'] ?? null) !== 'hybrid_verified') {
            throw new RuntimeException('The V2 hybrid benchmark corpus must be verified before policy resolution.');
        }
        $corpus->loadMissing([
            'embeddingSpaceGeneration.embeddingProfile',
            'sparseSpaceGeneration.sparseEmbeddingProfile',
        ]);
        if (
            $corpus->public_id !== ($state['generations']['hybrid_corpus']['public_id'] ?? null)
            || $corpus->sparseSpaceGeneration === null
        ) {
            throw new RuntimeException('The active corpus does not match the trusted benchmark provisioning record.');
        }
        $identity = [
            'version' => 'exp-0001-dolved-care-v2-observational',
            'reranker_provider' => 'voyage',
            'reranker_model' => 'rerank-2.5',
            'reranker_adapter_version' => '1',
            'embedding_profile_fingerprint' => $corpus->embeddingSpaceGeneration->embeddingProfile->fingerprint,
            'sparse_profile_fingerprint' => $corpus->sparseSpaceGeneration->sparseEmbeddingProfile->fingerprint,
            'fusion_strategy' => 'rrf',
            'fusion_version' => '1',
            'rrf_k' => 60,
            'dense_candidate_k' => 40,
            'sparse_candidate_k' => 40,
            'fusion_candidate_k' => 15,
            'reranker_candidate_k' => 15,
            'evidence_threshold' => 0.337890625,
            'final_evidence_k' => 5,
            'calibration_corpus_version' => EngineeringBenchmark::VERSION.'-foundation-experimental',
            'calibration_corpus_digest' => $state['benchmark']['digest'],
        ];
        $fingerprint = $this->canonical->digest($identity);
        $existing = EvidenceThresholdPolicy::query()->where('version', $identity['version'])->first();
        if ($existing !== null) {
            if (
                $existing->status !== EvidenceThresholdPolicyStatus::Calibrating
                || ! hash_equals($fingerprint, $existing->fingerprint)
            ) {
                throw new RuntimeException('The existing EXP-0001 policy is not the expected immutable CALIBRATING policy.');
            }

            return $existing;
        }

        $policy = new EvidenceThresholdPolicy($identity + [
            'status' => EvidenceThresholdPolicyStatus::Calibrating,
            'fingerprint' => $fingerprint,
        ]);
        $policy->public_id = (string) Str::uuid();
        $policy->save();

        return $policy;
    }
}
