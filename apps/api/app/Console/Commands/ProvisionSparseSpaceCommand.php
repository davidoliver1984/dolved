<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use App\Support\E2e\DeterministicRetrievalProfile;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionSparseSpaceCommand extends Command
{
    protected $signature = 'retrieval:provision-sparse-space
        {embedding-space : Compatible embedding-space generation public UUID}
        {--production-profile : Provision the production FastEmbed profile even in an isolated e2e runtime}';

    protected $description = 'Idempotently provision the accepted V1 SPLADE sparse space';

    public function handle(DeterministicRetrievalProfile $deterministicProfile): int
    {
        $embeddingSpacePublicId = (string) $this->argument('embedding-space');
        $e2e = app()->environment('e2e') && ! $this->option('production-profile');
        $e2eProfile = $e2e ? $deterministicProfile->load()['sparse'] : null;
        $profileIdentity = $e2e ? [
            ...$e2eProfile['profile'],
            'fingerprint' => $e2eProfile['fingerprint'],
            ...$e2eProfile['space'],
        ] : [
            'fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d',
            'provider' => 'fastembed',
            'model' => 'prithivida/Splade_PP_en_v1',
            'tokenizer' => 'bert-base-uncased',
            'tokenizer_revision' => null,
            'model_revision' => 'efcd182bc7eb351e81a9445752d4388c2bab500b',
            'adapter_version' => '1',
        ];
        Model::unguarded(function () use ($embeddingSpacePublicId, $profileIdentity): void {
            DB::transaction(function () use ($embeddingSpacePublicId, $profileIdentity): void {
                $dense = EmbeddingSpaceGeneration::query()
                    ->where('public_id', $embeddingSpacePublicId)
                    ->where('status', EmbeddingSpaceGenerationStatus::Available->value)
                    ->firstOrFail();
                $profile = SparseEmbeddingProfile::query()->firstOrCreate(
                    ['fingerprint' => $profileIdentity['fingerprint']],
                    [
                        'public_id' => (string) Str::uuid(),
                        'provider' => $profileIdentity['provider'],
                        'model' => $profileIdentity['model'],
                        'tokenizer' => $profileIdentity['tokenizer'],
                        'tokenizer_revision' => $profileIdentity['tokenizer_revision'],
                        'output_representation' => $profileIdentity['output_representation'] ?? 'sparse-index-weight',
                        'max_input_tokens' => $profileIdentity['max_input_tokens'] ?? 512,
                        'document_input_type' => $profileIdentity['document_input_type'] ?? 'document',
                        'query_input_type' => $profileIdentity['query_input_type'] ?? 'query',
                        'model_revision' => $profileIdentity['model_revision'],
                        'adapter_version' => $profileIdentity['adapter_version'],
                    ],
                );
                SparseSpaceGeneration::query()->firstOrCreate(
                    [
                        'sparse_embedding_profile_id' => $profile->id,
                        'embedding_space_generation_id' => $dense->id,
                        'vector_name' => $profileIdentity['vector_name'] ?? 'sparse',
                    ],
                    [
                        'public_id' => (string) Str::uuid(),
                        'status' => EmbeddingSpaceGenerationStatus::Available,
                        'available_at' => now(),
                    ],
                );
            });
        });
        $this->components->info('The V1 SPLADE sparse space is available for verified workspace rebuilds.');

        return self::SUCCESS;
    }
}
