<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvidenceThresholdPolicyStatus;
use Database\Factories\EvidenceThresholdPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'version',
    'fingerprint',
    'status',
    'reranker_provider',
    'reranker_model',
    'reranker_adapter_version',
    'embedding_profile_fingerprint',
    'sparse_profile_fingerprint',
    'fusion_strategy',
    'fusion_version',
    'rrf_k',
    'dense_candidate_k',
    'sparse_candidate_k',
    'fusion_candidate_k',
    'reranker_candidate_k',
    'evidence_threshold',
    'final_evidence_k',
    'calibration_corpus_version',
    'calibration_corpus_digest',
    'activated_at',
    'retired_at',
])]
class EvidenceThresholdPolicy extends Model
{
    /** @use HasFactory<EvidenceThresholdPolicyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (EvidenceThresholdPolicy $policy): void {
            if ($policy->exists && $policy->isDirty(array_diff($policy->getFillable(), [
                'status', 'activated_at', 'retired_at',
            ]))) {
                throw new LogicException('Evidence-threshold policy identity and calibration are immutable.');
            }
            $policy->assertBounds();
            $policy->assertLifecycleTimestamps();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => EvidenceThresholdPolicyStatus::class,
            'rrf_k' => 'integer',
            'dense_candidate_k' => 'integer',
            'sparse_candidate_k' => 'integer',
            'fusion_candidate_k' => 'integer',
            'reranker_candidate_k' => 'integer',
            'evidence_threshold' => 'float',
            'final_evidence_k' => 'integer',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    private function assertBounds(): void
    {
        if (
            $this->rrf_k < 1
            || $this->dense_candidate_k < 1
            || $this->sparse_candidate_k < 1
            || $this->fusion_candidate_k < 1
            || $this->fusion_candidate_k > $this->dense_candidate_k + $this->sparse_candidate_k
            || $this->reranker_candidate_k < 1
            || $this->reranker_candidate_k > $this->fusion_candidate_k
            || $this->final_evidence_k < 1
            || $this->final_evidence_k > $this->reranker_candidate_k
            || $this->evidence_threshold < 0
            || $this->evidence_threshold > 1
        ) {
            throw new LogicException('Evidence-threshold policy configuration is not monotonic.');
        }
    }

    private function assertLifecycleTimestamps(): void
    {
        $valid = match ($this->status) {
            EvidenceThresholdPolicyStatus::Calibrating => $this->activated_at === null
                && $this->retired_at === null,
            EvidenceThresholdPolicyStatus::Active => $this->activated_at !== null
                && $this->retired_at === null,
            EvidenceThresholdPolicyStatus::Retired => $this->retired_at !== null,
        };
        if (! $valid) {
            throw new LogicException('Evidence-threshold policy lifecycle timestamps do not match its status.');
        }
    }
}
