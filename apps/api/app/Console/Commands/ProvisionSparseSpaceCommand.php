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
        Model::unguarded(function () use ($embeddingSpacePublicId): void {
            DB::transaction(function () use ($embeddingSpacePublicId): void {
                $dense = EmbeddingSpaceGeneration::query()
                    ->where('public_id', $embeddingSpacePublicId)
                    ->where('status', EmbeddingSpaceGenerationStatus::Available->value)
                    ->firstOrFail();
                $profile = SparseEmbeddingProfile::query()->firstOrCreate(
                    ['fingerprint' => 'e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d'],
                    [
                        'public_id' => (string) Str::uuid(),
                        'provider' => 'fastembed',
                        'model' => 'prithivida/Splade_PP_en_v1',
                        'tokenizer' => 'bert-base-uncased',
                        'tokenizer_revision' => null,
                        'output_representation' => 'sparse-index-weight',
                        'max_input_tokens' => 512,
                        'document_input_type' => 'document',
                        'query_input_type' => 'query',
                        'model_revision' => 'efcd182bc7eb351e81a9445752d4388c2bab500b',
                        'adapter_version' => '1',
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
