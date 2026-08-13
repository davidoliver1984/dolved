<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use App\Models\WorkspaceCorpusGeneration;
use App\Support\Evaluation\BenchmarkCanonicalJson;
use App\Support\Evaluation\EngineeringBenchmark;
use App\Support\Evaluation\EngineeringBenchmarkState;
use App\Support\Evaluation\EngineeringRetrievalConfiguration;
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
        return $this->resolve(
            $corpus,
            EngineeringRetrievalConfiguration::VERSION,
            EngineeringRetrievalConfiguration::RRF_K,
        );
    }

    public function handleExp0003Control(WorkspaceCorpusGeneration $corpus): EvidenceThresholdPolicy
    {
        return $this->resolve($corpus, 'exp-0001-dolved-care-v2-observational', 60);
    }

    private function resolve(
        WorkspaceCorpusGeneration $corpus,
        string $version,
        int $rrfK,
    ): EvidenceThresholdPolicy {
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
            'version' => $version,
            'reranker_provider' => 'voyage',
            'reranker_model' => 'rerank-2.5',
            'reranker_adapter_version' => '1',
            'embedding_profile_fingerprint' => $corpus->embeddingSpaceGeneration->embeddingProfile->fingerprint,
            'sparse_profile_fingerprint' => $corpus->sparseSpaceGeneration->sparseEmbeddingProfile->fingerprint,
            'fusion_strategy' => 'rrf',
            'fusion_version' => '1',
            'rrf_k' => $rrfK,
            'dense_candidate_k' => 40,
            'sparse_candidate_k' => 40,
            'fusion_candidate_k' => 15,
            'reranker_candidate_k' => 15,
            'evidence_threshold' => EngineeringRetrievalConfiguration::EVIDENCE_THRESHOLD,
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
                throw new RuntimeException('The existing engineering policy is not the expected immutable CALIBRATING policy.');
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
