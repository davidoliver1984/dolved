<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionSparseSpaceCommand extends Command
{
    protected $signature = 'retrieval:provision-sparse-space
        {embedding-space : Compatible embedding-space generation public UUID}';

    protected $description = 'Idempotently provision the accepted V1 SPLADE sparse space';

    public function handle(): int
    {
        $embeddingSpacePublicId = (string) $this->argument('embedding-space');
        $e2e = app()->environment('e2e');
        $profileIdentity = $e2e ? [
            'fingerprint' => '182c7de0eb1db3e92830e3bab40bc45a113fa3c20866c0d3257a88d58b5c018f',
            'provider' => 'deterministic',
            'model' => 'sha256-sparse-v1',
            'tokenizer' => 'sha256-bytes',
            'tokenizer_revision' => '1',
            'model_revision' => '1',
            'adapter_version' => 'deterministic-v1',
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
                        'output_representation' => 'sparse-index-weight',
                        'max_input_tokens' => 512,
                        'document_input_type' => 'document',
                        'query_input_type' => 'query',
                        'model_revision' => $profileIdentity['model_revision'],
                        'adapter_version' => $profileIdentity['adapter_version'],
                    ],
                );
                SparseSpaceGeneration::query()->firstOrCreate(
                    [
                        'sparse_embedding_profile_id' => $profile->id,
                        'embedding_space_generation_id' => $dense->id,
                        'vector_name' => 'sparse',
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
