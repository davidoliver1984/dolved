<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EvidenceThresholdPolicyStatus;
use App\Models\EvidenceThresholdPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProvisionEvidenceThresholdPolicyCommand extends Command
{
    private const DENSE = 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c';

    private const SPARSE = 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d';

    private const FINGERPRINT = '8adb1a507bda52e7524903b77cc29dea7f602ad8f0d082c9e6421c07ed6227fb';

    private const VERSION = 'provisional-r28-query-evidence-contract-v1';

    protected $signature = 'retrieval:provision-evidence-threshold-policy
        {--dense-fingerprint= : Required dense embedding-profile fingerprint}
        {--sparse-fingerprint= : Required sparse embedding-profile fingerprint}';

    protected $description = 'Provision the provisional R28 document-aware Voyage policy with unchanged reviewed numeric values';

    public function handle(): int
    {
        try {
            if (! hash_equals(self::DENSE, (string) $this->option('dense-fingerprint'))
                || ! hash_equals(self::SPARSE, (string) $this->option('sparse-fingerprint'))) {
                throw new RuntimeException('Evidence-threshold policy profile fingerprints do not match the reviewed lineage.');
            }
            DB::transaction(function (): void {
                $expected = $this->expected();
                $collisions = EvidenceThresholdPolicy::query()
                    ->where(fn ($query) => $query
                        ->where('fingerprint', self::FINGERPRINT)
                        ->orWhere('version', self::VERSION))
                    ->lockForUpdate()
                    ->get();
                foreach ($collisions as $policy) {
                    foreach ($expected as $field => $value) {
                        if ((string) $policy->{$field} !== (string) $value) {
                            throw new RuntimeException('An incompatible evidence-threshold policy already occupies the provisional identity.');
                        }
                    }
                }
                $policy = $collisions->firstWhere('fingerprint', self::FINGERPRINT);
                if (! $policy instanceof EvidenceThresholdPolicy) {
                    $policy = new EvidenceThresholdPolicy;
                    $policy->forceFill($expected + [
                        'public_id' => (string) Str::uuid(),
                        'status' => EvidenceThresholdPolicyStatus::Calibrating,
                        'activated_at' => null,
                        'retired_at' => null,
                    ])->save();
                }
                if ($policy->status === EvidenceThresholdPolicyStatus::Retired) {
                    throw new RuntimeException('The provisional evidence-threshold policy has been retired and cannot be silently restored.');
                }
                if ($policy->status === EvidenceThresholdPolicyStatus::Calibrating) {
                    EvidenceThresholdPolicy::query()
                        ->where('status', EvidenceThresholdPolicyStatus::Active->value)
                        ->where('embedding_profile_fingerprint', self::DENSE)
                        ->where('sparse_profile_fingerprint', self::SPARSE)
                        ->where('id', '!=', $policy->id)
                        ->lockForUpdate()
                        ->get()
                        ->each(fn (EvidenceThresholdPolicy $active) => $active->update([
                            'status' => EvidenceThresholdPolicyStatus::Retired,
                            'retired_at' => now(),
                        ]));
                    $policy->update(['status' => EvidenceThresholdPolicyStatus::Active, 'activated_at' => now()]);
                }
            });
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info('The provisional R28 document-aware Voyage/SPLADE policy is active; reviewed numeric values are unchanged and are not broadly recalibrated.');

        return self::SUCCESS;
    }

    /** @return array<string, int|float|string> */
    private function expected(): array
    {
        return [
            'version' => self::VERSION,
            'fingerprint' => self::FINGERPRINT,
            'reranker_provider' => 'voyage',
            'reranker_model' => 'rerank-2.5',
            'reranker_adapter_version' => 'document-metadata-v2',
            'embedding_profile_fingerprint' => self::DENSE,
            'sparse_profile_fingerprint' => self::SPARSE,
            'fusion_strategy' => 'rrf',
            'fusion_version' => '1',
            'rrf_k' => 5,
            'dense_candidate_k' => 40,
            'sparse_candidate_k' => 40,
            'fusion_candidate_k' => 15,
            'reranker_candidate_k' => 15,
            'evidence_threshold' => 0.337890625,
            'final_evidence_k' => 5,
            'calibration_corpus_version' => 'v2-foundation-experimental',
            'calibration_corpus_digest' => 'aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d',
        ];
    }
}
