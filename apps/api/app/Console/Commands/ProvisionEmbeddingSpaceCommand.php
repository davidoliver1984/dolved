<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionEmbeddingSpaceCommand extends Command
{
    protected $signature = 'ingestion:provision-embedding-space';

    protected $description = 'Idempotently provision the accepted V1 embedding space';

    public function handle(): int
    {
        Model::unguarded(fn () => DB::transaction(function (): void {
            $profile = EmbeddingProfile::query()->firstOrCreate(
                ['fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c'],
                [
                    'public_id' => (string) Str::uuid(),
                    'provider' => 'voyage',
                    'model' => 'voyage-4-large',
                    'dimensions' => 1024,
                    'output_dtype' => 'float',
                    'document_input_type' => 'document',
                    'query_input_type' => 'query',
                    'normalisation' => 'unit_length',
                    'truncation' => false,
                    'model_revision' => null,
                    'adapter_version' => '1',
                ],
            );
            EmbeddingSpaceGeneration::query()->firstOrCreate(
                ['collection_name' => 'rag-platform-vectors-v1'],
                [
                    'public_id' => (string) Str::uuid(),
                    'embedding_profile_id' => $profile->id,
                    'vector_name' => 'dense',
                    'dimensions' => 1024,
                    'distance' => 'cosine',
                    'status' => EmbeddingSpaceGenerationStatus::Available,
                    'available_at' => now(),
                ],
            );
        }));
        $this->info('The V1 embedding space is available.');

        return self::SUCCESS;
    }
}
